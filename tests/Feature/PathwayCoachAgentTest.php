<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Tools\AssignActionTool;
use App\Ai\Tools\GetAssessmentResponsesTool;
use App\Ai\Tools\GetCurrentActionTool;
use App\Ai\Tools\GetGapsTool;
use App\Ai\Tools\GetHabitsTool;
use App\Ai\Tools\GetLockerTool;
use App\Ai\Tools\GetPathwayProfileTool;
use App\Ai\Tools\GetPlanTool;
use App\Ai\Tools\GetReadinessTool;
use App\Ai\Tools\GetStudentSignalsTool;
use App\Ai\Tools\PrescribeHabitTool;
use App\Ai\Tools\RecordGapTool;
use App\Ai\Tools\SaveToBrainTool;
use App\Ai\Tools\SearchBrainTool;
use App\Ai\Tools\SearchJobsTool;
use App\Ai\Tools\SetMilestoneTool;
use App\Enums\ConfidenceLevel;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ParentConsent;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Support\ActionQueue;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Storage\DatabaseConversationStore;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The student coach, forked from CareerCoachAgent.
 *
 * The fork exists because the audience changed, and the audience change is not
 * a matter of tone: a sixteen-year-old will organise their identity around an
 * authority-sounding sentence. Most of what is asserted here is therefore
 * about what the coach is structurally prevented from doing.
 */
class PathwayCoachAgentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A consented junior. The coach refuses to exist for anyone else, so
     * every case here needs a student who is actually entitled to one.
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

    protected function completedAssessment(User $user, ConfidenceLevel $confidence): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $assessment->vocationalProfile()->create([
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'secondary_orientation' => 'teaching',
            'mode_of_work' => 'hands-on',
            'confidence_level' => $confidence,
            'confidence_rationale' => 'Your answers were brief.',
            'missing_evidence' => ['More detail about what you have actually done.'],
        ]);

        return $assessment;
    }

    protected function answerWithSignal(Assessment $assessment): void
    {
        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Describe a time you helped someone.',
            'sort_order' => 1,
            'is_beta' => false,
        ]);

        $answer = Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => 'I just sat with her until she stopped crying.',
        ]);

        $assessment->signalExtractions()->create([
            'answer_id' => $answer->id,
            'type' => SignalType::Burden,
            'track' => SignalTrack::Demonstrated,
            'content' => 'Moved toward someone in distress.',
            'verbatim' => 'I just sat with her until she stopped crying',
            'sort_order' => 0,
        ]);
    }

    protected function section(string $method): string
    {
        $reflection = new ReflectionMethod(PathwayCoachAgent::class, $method);
        $reflection->setAccessible(true);

        return (string) $reflection->invoke(new PathwayCoachAgent($this->student()));
    }

    /**
     * The sections that demonstrate a register to imitate. A model shown
     * "you were born for this" in an example will produce it.
     *
     * @return array<string, array{0: string}>
     */
    public static function voiceSections(): array
    {
        return [
            'role' => ['role'],
            'opening move' => ['openingMove'],
            'gap types' => ['gapTypes'],
            'brain' => ['brain'],
            'voice' => ['voice'],
        ];
    }

    #[DataProvider('voiceSections')]
    public function test_the_sections_that_model_a_register_pass_the_red_team_lint(string $section): void
    {
        $this->assertSame([], RedTeamLint::blocking(RedTeamLint::inspect($this->section($section))));
    }

    /**
     * The other two sections are exempt by construction, not by convenience.
     * To forbid a phrase a prompt has to name it: "never reduce them to a
     * match score" necessarily contains "match score", and blueprint 10.5's
     * forbidden sentence has to be quoted to be prohibited. Linting them would
     * fail on the very instructions doing the protecting.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function quotedProhibitions(): array
    {
        return [
            'names the banned score language' => ['prohibitions', 'match score'],
            'names the forbidden dead-end sentence' => ['confidenceRules', 'not enough information to help them'],
        ];
    }

    #[DataProvider('quotedProhibitions')]
    public function test_the_forbidding_sections_name_what_they_forbid(string $section, string $phrase): void
    {
        $this->assertStringContainsString($phrase, $this->section($section));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredProhibitions(): array
    {
        return [
            'refuses to speak for God' => ['Never speak for God'],
            'refuses destiny' => ['Never claim destiny'],
            'refuses to prescribe a career' => ['Never tell them what they should become'],
            'refuses numbers' => ['Never reduce them to a number'],
            'refuses clinical language' => ['Never use clinical or diagnostic language'],
            'refuses flattery' => ['Never flatter'],
            'refuses to do the work for them' => ['Never do the work for them'],
        ];
    }

    #[DataProvider('requiredProhibitions')]
    public function test_the_coach_is_told_what_it_may_never_do(string $prohibition): void
    {
        $this->assertStringContainsString(
            $prohibition,
            (string) (new PathwayCoachAgent($this->student()))->instructions(),
        );
    }

    /**
     * Blueprint 10.5. The forbidden sentence and the mandated one are a clause
     * apart, so the instruction has to carry both.
     */
    public function test_the_coach_is_given_the_low_confidence_script(): void
    {
        $instructions = (string) (new PathwayCoachAgent($this->student()))->instructions();

        $this->assertStringContainsString('Never say there is not enough information to help them', $instructions);
        $this->assertStringContainsString('enough to know what we need to test next', $instructions);
    }

    public function test_the_coach_is_told_to_end_with_one_thing_not_a_list(): void
    {
        $instructions = (string) (new PathwayCoachAgent($this->student()))->instructions();

        $this->assertStringContainsString('End with one thing', $instructions);
        $this->assertStringContainsString('a list is the friction you are here to remove', $instructions);
    }

    public function test_the_coach_is_told_to_name_the_six_gap_types(): void
    {
        $instructions = (string) (new PathwayCoachAgent($this->student()))->instructions();

        foreach (['Information', 'Access', 'Finances', 'Habits', 'Relationships', 'Future outlook'] as $gap) {
            $this->assertStringContainsString($gap, $instructions);
        }
    }

    public function test_the_coach_is_told_to_handle_crisis_before_vocation(): void
    {
        $instructions = (string) (new PathwayCoachAgent($this->student()))->instructions();

        $this->assertStringContainsString('set the', $instructions);
        $this->assertStringContainsString('crisis, hopelessness or self-harm', $instructions);
    }

    /**
     * The student coach must not inherit the adult coach's job-search tooling;
     * that would quietly turn it into a job board for sixteen-year-olds.
     */
    public function test_it_carries_only_the_student_facing_tools(): void
    {
        $tools = collect((new PathwayCoachAgent($this->student()))->tools())
            ->map(fn ($tool) => $tool::class)
            ->all();

        $this->assertEqualsCanonicalizing(
            [
                GetPathwayProfileTool::class,
                GetAssessmentResponsesTool::class,
                GetStudentSignalsTool::class,
                GetGapsTool::class,
                RecordGapTool::class,
                GetCurrentActionTool::class,
                AssignActionTool::class,
                GetReadinessTool::class,
                GetHabitsTool::class,
                PrescribeHabitTool::class,
                GetPlanTool::class,
                SetMilestoneTool::class,
                GetLockerTool::class,
                SearchBrainTool::class,
                SaveToBrainTool::class,
            ],
            $tools,
        );
        $this->assertNotContains(SearchJobsTool::class, $tools);
    }

    /**
     * The prompt has to say what the brain is for, or the tool sits unused and
     * the coach answers "what have I said about this?" from its own context —
     * which holds this conversation, not this year.
     */
    public function test_it_tells_the_coach_to_hand_the_students_words_back_unaltered(): void
    {
        $brain = preg_replace('/\s+/u', ' ', strtolower($this->section('brain')));

        $this->assertStringContainsString('do not smooth the grammar', $brain);
        $this->assertStringContainsString('never fill the silence', $brain);
    }

    /**
     * Capture lives on the agent's own entry point rather than in routing,
     * because a conversation that happens before somebody remembers to add the
     * call is gone permanently.
     */
    public function test_responding_to_a_student_captures_their_turn_before_anything_else(): void
    {
        $student = $this->student();
        $agent = new PathwayCoachAgent($student);

        try {
            $agent->respondTo('i keep thinking about the week where nobody knew what was wrong yet');
        } catch (\Throwable) {
            // The model call is not under test; the capture that precedes it is.
        }

        $this->assertSame(
            'i keep thinking about the week where nobody knew what was wrong yet',
            $student->brainEntries()->sole()->content,
        );
    }

    public function test_the_profile_tool_refuses_to_permit_a_conclusion_at_low_confidence(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Weak);

        $payload = json_decode((new GetPathwayProfileTool($student))->handle(new Request([])), true);

        $this->assertTrue($payload['has_profile']);
        $this->assertFalse($payload['may_name_a_direction']);
        $this->assertNotEmpty($payload['missing_evidence']);
    }

    public function test_the_profile_tool_permits_a_conclusion_when_the_evidence_supports_one(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Strong);

        $payload = json_decode((new GetPathwayProfileTool($student))->handle(new Request([])), true);

        $this->assertTrue($payload['may_name_a_direction']);
    }

    /**
     * A confidence label is words, never a number — a coach handed "72" will
     * eventually repeat it.
     */
    public function test_the_profile_tool_reports_confidence_in_words_not_numbers(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Emerging);

        $payload = json_decode((new GetPathwayProfileTool($student))->handle(new Request([])), true);

        $this->assertMatchesRegularExpression('/^[^\d]+$/', $payload['confidence_label']);
    }

    public function test_the_profile_tool_tells_the_coach_not_to_guess_without_an_assessment(): void
    {
        $payload = json_decode((new GetPathwayProfileTool($this->student()))->handle(new Request([])), true);

        $this->assertFalse($payload['has_profile']);
        $this->assertStringContainsString('Do not guess', $payload['guidance']);
    }

    /**
     * The only quotes the coach can reach are verified spans. This is what
     * makes "does not write what they would have said" enforceable rather
     * than merely instructed.
     */
    public function test_the_signals_tool_returns_only_verified_verbatim_spans(): void
    {
        $student = $this->student();
        $assessment = $this->completedAssessment($student, ConfidenceLevel::Moderate);
        $this->answerWithSignal($assessment);

        $payload = json_decode((new GetStudentSignalsTool($student))->handle(new Request([])), true);

        $this->assertCount(1, $payload['demonstrated']);
        $this->assertSame('I just sat with her until she stopped crying', $payload['demonstrated'][0]['said']);
        $this->assertSame([], $payload['aspiration']);
        $this->assertStringContainsString('literal words', $payload['guidance']);
    }

    public function test_the_signals_tool_tells_the_coach_not_to_invent_when_it_has_nothing(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Weak);

        $payload = json_decode((new GetStudentSignalsTool($student))->handle(new Request([])), true);

        $this->assertSame([], $payload['signals']);
        $this->assertStringContainsString('Do not invent', $payload['guidance']);
    }

    public function test_it_keeps_the_conversation_memory_the_fork_was_built_on(): void
    {
        $agent = new PathwayCoachAgent($this->student());

        $this->assertInstanceOf(Conversational::class, $agent);
        $this->assertSame(50, $agent->maxConversationMessages());
    }

    protected function instructionsFor(PathwayCoachAgent $agent): string
    {
        return (string) $agent->instructions();
    }

    /**
     * The convergence rule, which exists because the first live conversation
     * ran three turns and recorded nothing.
     */
    public function test_it_tells_the_coach_which_exchange_this_is(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Moderate);

        $instructions = $this->instructionsFor(new PathwayCoachAgent($student));

        $this->assertStringContainsString('This is exchange 1 with this student.', $instructions);
        $this->assertStringContainsString('They have no step in progress right now.', $instructions);
    }

    public function test_the_exchange_number_counts_the_turns_already_taken(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Moderate);

        $store = new DatabaseConversationStore;
        $conversationId = $store->storeConversation($student->id, 'A session');

        foreach (['first thing they said', 'second thing they said'] as $content) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $student->id,
                'agent' => PathwayCoachAgent::class,
                'role' => 'user',
                'content' => $content,
                'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]',
                'usage' => '[]', 'meta' => '[]',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $agent = (new PathwayCoachAgent($student))->continue($conversationId, as: $student);

        $this->assertStringContainsString('This is exchange 3 with this student.', $this->instructionsFor($agent));
    }

    /**
     * Assistant turns are not exchanges. Counting them would double the number
     * and push the coach to assign a step before it has asked anything.
     */
    public function test_the_coachs_own_replies_do_not_advance_the_exchange_count(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Moderate);

        $store = new DatabaseConversationStore;
        $conversationId = $store->storeConversation($student->id, 'A session');

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $student->id,
            'agent' => PathwayCoachAgent::class,
            'role' => 'assistant',
            'content' => 'What did you notice first?',
            'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]',
            'usage' => '[]', 'meta' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $agent = (new PathwayCoachAgent($student))->continue($conversationId, as: $student);

        $this->assertStringContainsString('This is exchange 1 with this student.', $this->instructionsFor($agent));
    }

    public function test_it_names_the_step_already_in_progress_rather_than_stacking_another(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Moderate);

        (new ActionQueue)->assign($student, 'Ask the nurse from your placement what her first year was like');

        $instructions = $this->instructionsFor(new PathwayCoachAgent($student));

        $this->assertStringContainsString('Ask the nurse from your placement', $instructions);
        $this->assertStringContainsString('do not stack another on top', $instructions);
    }

    /**
     * A student in distress outranks the convergence rule, and the prompt has
     * to say so in the same breath, or the rule reads as unconditional.
     */
    public function test_the_convergence_rule_yields_to_a_student_in_distress(): void
    {
        $instructions = $this->instructionsFor(new PathwayCoachAgent($this->student()));

        $this->assertStringContainsString('do not end a reply without a step', $instructions);
        $this->assertStringContainsString('painful or urgent', $instructions);
    }

    /**
     * Nine tools give a default budget of fourteen steps, and a turn that
     * reads six of them and writes one can spend the lot without ever
     * speaking. A live run did exactly that and returned an empty message.
     */
    public function test_it_allows_more_steps_than_a_tool_heavy_turn_can_spend(): void
    {
        $attributes = (new ReflectionClass(PathwayCoachAgent::class))->getAttributes(MaxSteps::class);

        $this->assertNotEmpty($attributes, 'The coach relies on the default step budget.');

        $budget = $attributes[0]->newInstance()->value;
        $tools = count(iterator_to_array((new PathwayCoachAgent($this->student()))->tools()));

        $this->assertGreaterThan(
            (int) round($tools * 1.5),
            $budget,
            'The step budget is no better than the default it was raised to beat.',
        );
    }

    /**
     * The recovery must not fire on an ordinary turn — re-prompting a coach
     * that already answered would double every conversation's cost and post a
     * second reply on top of the first.
     */
    public function test_a_turn_that_said_something_is_returned_untouched(): void
    {
        $agent = new PathwayCoachAgent($this->student());

        $spoke = new AgentResponse('inv-1', 'What did you notice first?', new Usage(0, 0), new Meta('m', 'p'));

        $method = new ReflectionMethod($agent, 'ensureTheCoachActuallySpoke');
        $method->setAccessible(true);

        $this->assertSame($spoke, $method->invoke($agent, $spoke));
    }

    /**
     * The shape of what the SDK hands back, pinned as a contract.
     *
     * `text` and `conversationId` are public properties, not accessors. Both
     * coach controllers called them as methods, which cannot work in any
     * version — and because the call sat inside a `catch (\Throwable)` that
     * returns a generic 503, the fatal turned into a permanent "temporarily
     * unavailable" that pointed at the provider instead of at us.
     *
     * Asserting it here means an SDK change breaks a test rather than a
     * student's conversation.
     */
    public function test_the_sdk_hands_back_text_and_a_conversation_id_as_properties(): void
    {
        $response = new AgentResponse('inv-1', 'What did you notice first?', new Usage(0, 0), new Meta('m', 'p'));

        $this->assertSame('What did you notice first?', $response->text);
        $this->assertNull($response->conversationId);
        $this->assertFalse(method_exists($response, 'text'));
        $this->assertFalse(method_exists($response, 'conversationId'));
    }
}
