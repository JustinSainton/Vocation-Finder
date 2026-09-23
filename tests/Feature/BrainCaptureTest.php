<?php

namespace Tests\Feature;

use App\Ai\Tools\SaveToBrainTool;
use App\Ai\Tools\SearchBrainTool;
use App\Enums\BrainEntrySource;
use App\Models\Action;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\BrainEntry;
use App\Models\ParentConsent;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Support\AccessPolicy;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\BrainRetrieval;
use App\Support\ParentVisibility;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The vocational brain: a student's own words, kept.
 *
 * Two properties are load-bearing and are tested here as hard constraints
 * rather than as behaviour:
 *
 * 1. **It never writes for them.** Everything stored traces to something the
 *    student actually said, and nothing can rewrite it afterwards.
 * 2. **It is never destroyed.** Deletion is refused at the model, and reading
 *    and export consult no entitlement at all.
 */
class BrainCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected BrainCapture $capture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capture = new BrainCapture;
    }

    /**
     * A junior with consent — the tier that actually has a brain.
     */
    protected function student(): User
    {
        $student = User::factory()->paying()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);

        ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ])->grant();

        return $student->fresh();
    }

    public function test_it_keeps_what_a_student_said_to_the_coach(): void
    {
        $student = $this->student();

        $entry = $this->capture->captureCoachTurn(
            $student,
            role: 'user',
            content: 'I liked the hospital week more than I expected, mostly the part where nobody knew what was wrong yet.',
            prompt: 'What surprised you?',
        );

        $this->assertNotNull($entry);
        $this->assertSame(BrainEntrySource::Coach, $entry->source);
        $this->assertStringContainsString('nobody knew what was wrong yet', $entry->content);
        $this->assertSame('What surprised you?', $entry->context);
        $this->assertNotNull($entry->occurred_at);
    }

    /**
     * The whole line, in one test. The coach's sentences are not the student's
     * words and must never end up filed as theirs.
     */
    public function test_it_never_keeps_the_coaches_own_words(): void
    {
        $student = $this->student();

        $entry = $this->capture->captureCoachTurn(
            $student,
            role: 'assistant',
            content: 'It sounds like you are drawn to work where the answer is not known in advance.',
        );

        $this->assertNull($entry);
        $this->assertSame(0, BrainEntry::count());
    }

    public function test_it_skips_acknowledgements_that_carry_nothing(): void
    {
        $student = $this->student();

        $this->assertNull($this->capture->captureCoachTurn($student, 'user', 'yeah idk'));
        $this->assertNull($this->capture->captureCoachTurn($student, 'user', 'ok sure'));
        $this->assertSame(0, BrainEntry::count());
    }

    /**
     * The floor is on material, not on polish. Blueprint §11: answer quality is
     * specificity and grounding, never writing skill.
     */
    public function test_it_keeps_a_substantive_turn_written_without_any_polish(): void
    {
        $student = $this->student();

        $entry = $this->capture->captureCoachTurn(
            $student,
            'user',
            'i dont like when its all planned out i want to figure out the thing myself',
        );

        $this->assertNotNull($entry);
    }

    public function test_it_stores_words_exactly_as_written(): void
    {
        $student = $this->student();
        $said = 'i just sat with her until she stopped crying, thats all i did';

        $entry = $this->capture->captureCoachTurn($student, 'user', $said);

        $this->assertSame($said, $entry->content);
    }

    public function test_a_brain_entry_cannot_be_rewritten(): void
    {
        $student = $this->student();
        $entry = $this->capture->captureCoachTurn($student, 'user', 'i just sat with her until she stopped crying');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot be rewritten/');

        $entry->update(['content' => 'I comforted a classmate who was upset.']);
    }

    public function test_a_brain_entry_can_still_gain_context_without_touching_the_words(): void
    {
        $student = $this->student();
        $entry = $this->capture->captureCoachTurn($student, 'user', 'i just sat with her until she stopped crying');

        $entry->update(['context' => 'What is something you did that felt easy?']);

        $this->assertSame('i just sat with her until she stopped crying', $entry->fresh()->content);
        $this->assertSame('What is something you did that felt easy?', $entry->fresh()->context);
    }

    public function test_a_brain_entry_is_never_deleted(): void
    {
        $student = $this->student();
        $entry = $this->capture->captureCoachTurn($student, 'user', 'i want to know if i can actually do this job');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/never deleted/');

        $entry->delete();
    }

    public function test_it_captures_voice_as_well_as_text(): void
    {
        $student = $this->student();

        $entry = $this->capture->captureCoachTurn(
            $student,
            'user',
            'the part i keep coming back to is the people who had nobody with them',
            audioStoragePath: 'brain/audio/turn-1.webm',
        );

        $this->assertSame('brain/audio/turn-1.webm', $entry->audio_storage_path);
    }

    public function test_completing_an_action_captures_the_reflection(): void
    {
        $student = $this->student();
        $queue = new ActionQueue;

        $action = $queue->assign($student, 'Ask your aunt what her hardest week looks like.');
        $queue->complete($action, 'she said the hard part isnt the blood its telling someone bad news and i think i could do that');

        $entry = $student->brainEntries()->first();

        $this->assertNotNull($entry);
        $this->assertSame(BrainEntrySource::Action, $entry->source);
        $this->assertStringContainsString('telling someone bad news', $entry->content);
        $this->assertTrue($entry->action->is($action));
        $this->assertStringContainsString('Ask your aunt', $entry->context);
    }

    public function test_capturing_a_reflection_is_idempotent(): void
    {
        $student = $this->student();
        $action = (new ActionQueue)->assign($student, 'Ask your aunt what her hardest week looks like.');
        (new ActionQueue)->complete($action, 'she said the hard part is telling someone bad news and i think i could do that');

        $this->capture->captureActionReflection($action->fresh());
        $this->capture->captureActionReflection($action->fresh());

        $this->assertSame(1, BrainEntry::count());
    }

    /**
     * Settling an action must never fail because the brain refused the write.
     * Consent can be revoked between assignment and completion, and the
     * student still finished the thing.
     */
    public function test_a_refused_capture_does_not_block_settling_an_action(): void
    {
        $student = User::factory()->create(['grade_level' => 9]);
        $action = Action::create([
            'user_id' => $student->id,
            'title' => 'Ask your aunt what her hardest week looks like.',
            'assigned_at' => now(),
        ]);

        $settled = (new ActionQueue)->complete($action, 'it went better than i thought it would honestly');

        $this->assertTrue($settled->status->isSettled());
        $this->assertSame(0, BrainEntry::count());
    }

    public function test_a_freshman_has_no_brain_to_write_to(): void
    {
        $freshman = User::factory()->create(['grade_level' => 9]);

        $this->expectException(RuntimeException::class);

        $this->capture->captureDirect($freshman, 'i think i want to build things for a living');
    }

    /**
     * A deliberate save has no length floor. If a student chooses to keep four
     * words, those four words matter to them.
     */
    public function test_a_deliberate_save_keeps_something_shorter_than_the_floor(): void
    {
        $student = $this->student();

        $entry = $this->capture->captureDirect($student, 'i want to matter');

        $this->assertSame('i want to matter', $entry->content);
        $this->assertSame(BrainEntrySource::Direct, $entry->source);
    }

    public function test_it_will_not_keep_an_empty_entry(): void
    {
        $this->expectException(RuntimeException::class);

        $this->capture->captureDirect($this->student(), '   ');
    }

    protected function assessmentFor(User $student, string ...$answers): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        foreach ($answers as $index => $text) {
            $question = Question::create([
                'category_id' => $category->id,
                'question_text' => 'What is something you did that felt easy?',
                'sort_order' => $index + 1,
                'is_beta' => false,
            ]);

            Answer::create([
                'assessment_id' => $assessment->id,
                'question_id' => $question->id,
                'response_text' => $text,
            ]);
        }

        return $assessment->fresh();
    }

    /**
     * A student who finishes the assessment and opens the brain to an empty
     * page has been told the product remembers them and shown that it does
     * not.
     */
    public function test_it_seeds_the_brain_from_the_assessment(): void
    {
        $student = $this->student();
        $assessment = $this->assessmentFor(
            $student,
            'i fixed my neighbours mower because nobody else was going to and it took me all saturday',
            'ok',
        );

        $entries = $this->capture->captureAssessment($assessment);

        $this->assertCount(1, $entries);
        $this->assertSame(BrainEntrySource::Assessment, $entries[0]->source);
        $this->assertStringContainsString('all saturday', $entries[0]->content);
        $this->assertSame('What is something you did that felt easy?', $entries[0]->context);
        $this->assertTrue($entries[0]->assessment->is($assessment));
    }

    public function test_seeding_the_brain_twice_does_not_duplicate_it(): void
    {
        $student = $this->student();
        $assessment = $this->assessmentFor($student, 'i fixed my neighbours mower because nobody else was going to');

        $this->capture->captureAssessment($assessment);
        $this->capture->captureAssessment($assessment);

        $this->assertSame(1, BrainEntry::count());
    }

    public function test_a_guest_assessment_seeds_nothing(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $this->assertSame([], $this->capture->captureAssessment($assessment));
    }

    public function test_search_returns_the_students_own_words_unaltered(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'the hospital thing was the only week i didnt want to leave early');
        $this->capture->captureDirect($student, 'i hated the office one, everybody was just waiting for lunch');

        $found = (new BrainRetrieval)->search($student, 'hospital');

        $this->assertCount(1, $found);
        $this->assertSame('the hospital thing was the only week i didnt want to leave early', $found->first()->content);
    }

    public function test_search_ignores_words_too_common_to_narrow_anything(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'the thing about the office was that the people were fine');

        $this->assertCount(1, (new BrainRetrieval)->search($student, 'what did they say about the office'));
    }

    public function test_export_works_regardless_of_entitlement(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i want to work somewhere the answer isnt known yet');

        $student->parentConsents()->first()->revoke();

        $export = (new BrainRetrieval)->export($student->fresh());

        $this->assertCount(1, $export);
        $this->assertSame('i want to work somewhere the answer isnt known yet', $export[0]['in_their_words']);
    }

    public function test_export_is_chronological(): void
    {
        $student = $this->student();
        $first = $this->capture->captureDirect($student, 'i think i want to do something with my hands');
        $first->forceFill(['occurred_at' => now()->subMonths(9)])->saveQuietly();
        $this->capture->captureDirect($student, 'i keep coming back to the diagnostic part of it');

        $export = (new BrainRetrieval)->export($student->fresh());

        $this->assertStringContainsString('with my hands', $export[0]['in_their_words']);
        $this->assertStringContainsString('diagnostic part', $export[1]['in_their_words']);
    }

    /**
     * The freeze-not-delete policy, stated as the two halves it actually has.
     */
    public function test_a_lapse_freezes_capture_but_not_reading_or_export(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i want to work somewhere the answer isnt known yet');

        $student->parentConsents()->first()->revoke();
        $student = $student->fresh();

        $this->assertTrue(AccessPolicy::brainIsFrozen($student));
        $this->assertTrue(AccessPolicy::canExportBrain($student));
        $this->assertCount(1, (new BrainRetrieval)->recent($student));

        $this->expectException(RuntimeException::class);
        $this->capture->captureDirect($student, 'and this should not go in');
    }

    /**
     * The export route checks nothing on purpose. We promise a parent in
     * writing, at the moment they consent, that nothing is ever deleted.
     */
    public function test_export_is_reachable_with_no_consent_and_no_subscription(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i keep coming back to the part nobody has figured out yet');
        $student->parentConsents()->first()->revoke();

        $response = $this->actingAs($student->fresh())->get('/brain/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'i keep coming back to the part nobody has figured out yet',
            $response->streamedContent(),
        );
    }

    public function test_export_hands_back_their_words_without_writing_any(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i dont want a job where i already know how every day ends');

        $body = $this->actingAs($student)->get('/brain/export')->streamedContent();

        $this->assertStringContainsString('in your own words', $body);
        $this->assertStringContainsString('Nothing here was written for you.', $body);
        $this->assertStringContainsString('> i dont want a job where i already know how every day ends', $body);
    }

    public function test_export_is_available_as_json_too(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i dont want a job where i already know how every day ends');

        $body = $this->actingAs($student)->get('/brain/export?format=json')->streamedContent();

        $this->assertSame(
            'i dont want a job where i already know how every day ends',
            json_decode($body, true)[0]['in_their_words'],
        );
    }

    /**
     * An empty brain must not read as a failure. A student who has said
     * nothing yet has not done anything wrong.
     */
    public function test_an_empty_export_still_says_something_kind(): void
    {
        $body = $this->actingAs($this->student())->get('/brain/export')->streamedContent();

        $this->assertStringContainsString('it is yours when it does', $body);
        $this->assertSame([], RedTeamLint::blocking(RedTeamLint::inspect($body)));
    }

    public function test_a_parent_never_sees_the_brain(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'i am scared i will pick wrong and waste four years');

        $summary = ParentVisibility::summaryFor($student->fresh());
        $encoded = json_encode($summary);

        $this->assertStringNotContainsString('waste four years', $encoded);

        foreach (ParentVisibility::FORBIDDEN_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $summary);
        }
    }

    public function test_the_save_tool_refuses_words_the_student_did_not_write(): void
    {
        $student = $this->student();
        $tool = new SaveToBrainTool($student, 'i just sat with her until she stopped crying, thats all i did');

        $result = json_decode($tool->handle(new Request([
            'student_words' => 'I sat with a classmate who was upset until she calmed down.',
        ])), true);

        $this->assertFalse($result['saved']);
        $this->assertStringContainsString('not what they wrote', $result['guidance']);
        $this->assertSame(0, BrainEntry::count());
    }

    public function test_the_save_tool_keeps_a_span_the_student_did_write(): void
    {
        $student = $this->student();
        $tool = new SaveToBrainTool($student, 'i just sat with her until she stopped crying, thats all i did');

        $result = json_decode($tool->handle(new Request([
            'student_words' => 'i just sat with her until she stopped crying',
        ])), true);

        $this->assertTrue($result['saved']);
        $this->assertSame('i just sat with her until she stopped crying', BrainEntry::first()->content);
    }

    public function test_the_search_tool_tells_the_coach_not_to_invent_a_past(): void
    {
        $result = json_decode((new SearchBrainTool($this->student()))->handle(new Request([
            'topic' => 'money',
        ])), true);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Do not invent', $result['guidance']);
    }

    public function test_the_search_tool_tells_the_coach_not_to_tidy_their_words(): void
    {
        $student = $this->student();
        $this->capture->captureDirect($student, 'money is the thing nobody in my house will actually talk about');

        $result = json_decode((new SearchBrainTool($student))->handle(new Request([
            'topic' => 'money',
        ])), true);

        $this->assertTrue($result['found']);
        $this->assertSame(
            'money is the thing nobody in my house will actually talk about',
            $result['entries'][0]['in_their_words'],
        );
        $this->assertStringContainsString('do not tidy the grammar', strtolower($result['guidance']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sources(): array
    {
        return [
            'direct' => ['direct'],
            'coach' => ['coach'],
            'action' => ['action'],
            'assessment' => ['assessment'],
        ];
    }

    #[DataProvider('sources')]
    public function test_every_source_is_attributable_in_plain_language(string $source): void
    {
        $label = BrainEntrySource::from($source)->label();

        $this->assertNotSame('', $label);
        $this->assertStringNotContainsString('_', $label);
    }
}
