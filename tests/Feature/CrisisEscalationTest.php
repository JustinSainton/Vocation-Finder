<?php

namespace Tests\Feature;

use App\Enums\CrisisStanding;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\BrainEntry;
use App\Models\FeatureFlag;
use App\Models\ParentConsent;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Support\ConversationLocale;
use App\Support\CrisisCheck;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The safety invariant, finally enforced in code.
 *
 * "Crisis content escalates to human support before vocational
 * interpretation" has been a non-negotiable since the blueprint, and until now
 * it lived only in the coach's prompt — which means it lived nowhere. A prompt
 * is a request. This is the one failure in the product that must not depend on
 * a model choosing to comply, so it is a lint that runs before the network
 * call rather than a sentence the model reads on the way to answering.
 *
 * Almost everything here is about *order*: before the model, before the
 * entitlement check, before the portrait, before anything this product sells.
 */
class CrisisEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    /**
     * A consented junior: someone the coach would actually answer, so that a
     * refusal to answer can only be the escalation.
     */
    protected function entitledStudent(): User
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

    /**
     * The whole point. The coach is never constructed, so the model is never
     * called, so nothing about this sentence is interpreted as a career clue.
     *
     * Asserted through the AI SDK's conversation store: a turn that reached
     * the model leaves a conversation behind, and this one must leave none.
     */
    #[Test]
    public function a_message_that_points_to_crisis_never_reaches_the_model(): void
    {
        $this->enableCoach();
        $student = $this->entitledStudent();

        $response = $this->actingAs($student)
            ->post('/coach/message', ['message' => 'I keep thinking I want to die and I do not know who to tell.']);

        $response->assertRedirect();
        $response->assertSessionHas('support');

        $this->assertDatabaseCount('agent_conversations', 0);
    }

    /**
     * And their words are still kept. The brain is never allowed to lose what
     * somebody said, and this is the sentence a student would least want to
     * have to write a second time.
     */
    #[Test]
    public function the_words_are_still_captured_to_the_brain(): void
    {
        $this->enableCoach();
        $student = $this->entitledStudent();

        $this->actingAs($student)
            ->post('/coach/message', ['message' => 'Honestly some days I want to die and school feels pointless.']);

        $this->assertSame(1, BrainEntry::query()->where('user_id', $student->id)->count());
    }

    /**
     * Before the entitlement check, not after it. A freshman, a lapsed
     * subscription and an unconsented junior all get the phone number: the one
     * thing in this product that is not a feature is not behind the paywall
     * either.
     */
    #[Test]
    public function support_reaches_a_student_the_coach_would_refuse(): void
    {
        $this->enableCoach();

        // A freshman: entitled to a portrait, never to a coach, at any price.
        $freshman = User::factory()->create([
            'grade_level' => 9,
            'birthdate' => now()->subYears(14)->toDateString(),
        ]);

        $response = $this->actingAs($freshman)
            ->post('/coach/message', ['message' => 'I want to kill myself.']);

        $response->assertSessionHas('support');
        $response->assertSessionMissing('status');
    }

    /**
     * Nobody is told. Parents never see coaching conversations, and a tool
     * that quietly reported what a sixteen-year-old typed to their school
     * would be the last thing any of them typed honestly. Escalation means the
     * student is handed a route to a person — it is not a disclosure about
     * them.
     */
    #[Test]
    public function escalation_notifies_nobody(): void
    {
        Notification::fake();
        $this->enableCoach();
        $student = $this->entitledStudent();

        $this->actingAs($student)
            ->post('/coach/message', ['message' => 'I want to kill myself.']);

        Notification::assertNothingSent();
    }

    /**
     * Every locale's list is read regardless of the one the student is set to.
     * A bilingual student switches language for exactly the things that are
     * hardest to say, and reading the wrong list because a dropdown said
     * "English" would be the worst possible moment to be tidy about it.
     */
    #[Test]
    public function the_lists_are_read_in_every_supported_language(): void
    {
        $check = new CrisisCheck;

        foreach ([
            'No quiero vivir asi, quiero morir.',
            'Nao aguento mais, quero morrer.',
            'Ya no puedo mas con nada.',
        ] as $message) {
            $this->assertSame(
                CrisisStanding::Escalate,
                $check->standing($message),
                "Missed: {$message}",
            );
        }
    }

    /**
     * Accents are folded before matching, because somebody writing this is not
     * reaching for the accent key.
     */
    #[Test]
    public function an_unaccented_message_is_read_the_same_way(): void
    {
        $check = new CrisisCheck;

        $this->assertSame(CrisisStanding::Escalate, $check->standing('nao aguento mais'));
        $this->assertSame(CrisisStanding::Escalate, $check->standing('não aguento mais'));
    }

    /**
     * A student reporting what a friend said is a student who needs to know
     * where to send them. The support text is written to cover both, so it
     * does not have to guess which one this is.
     */
    #[Test]
    public function a_disclosure_about_somebody_else_escalates_too(): void
    {
        $check = new CrisisCheck;

        $this->assertSame(
            CrisisStanding::Escalate,
            $check->standing('my friend told me he wants to die and I did not know what to say'),
        );
    }

    /**
     * Ordinary answers about work must not trip it. A crisis message that
     * arrives for no reason teaches a student that this one is noise, which is
     * the only way a true positive gets ignored.
     */
    #[Test]
    public function ordinary_vocational_language_does_not_escalate(): void
    {
        $check = new CrisisCheck;

        foreach ([
            'This homework is killing me but I like the class.',
            // The phrase "want to die" sits inside "want to diet", which is
            // why matching is whole-word: a nutrition answer must not trip it.
            'I do not want to diet forever, I want to understand nutrition properly.',
            'I am dying to try the robotics club.',
            'I would die of embarrassment if I had to present it.',
            'My grandmother died last year and I helped look after her.',
            'I want to be a paramedic because I am not scared in an emergency.',
            'The overdose scene in that documentary is what made me interested in nursing.',
            'Me encanta cuidar a mi abuela aunque me canso mucho.',
            'Eu quero trabalhar com pessoas que precisam de ajuda.',
        ] as $message) {
            $this->assertSame(
                CrisisStanding::None,
                $check->standing($message),
                "False positive: {$message}",
            );
        }
    }

    /**
     * And one accepted false positive, named rather than hidden. A student who
     * wants to work *in* suicide prevention gets the support block, which
     * costs them one reply about careers.
     *
     * That is the trade this class is built on: the two errors are not
     * comparable, so the check is deliberately over-eager and the message is
     * written so that receiving it wrongly is not an accusation.
     */
    #[Test]
    public function an_accepted_false_positive_is_named_rather_than_hidden(): void
    {
        $this->assertSame(
            CrisisStanding::Escalate,
            (new CrisisCheck)->standing('I want to work in suicide prevention one day.'),
        );
    }

    /**
     * The message itself is user-facing language, so it obeys the same rules
     * as everything else the product says, and it has to actually name a way
     * to reach a person — a message that says "please talk to someone" without
     * saying who is a message that does nothing.
     */
    #[Test]
    public function the_support_message_names_a_person_and_passes_the_lint(): void
    {
        foreach (ConversationLocale::supported() as $locale) {
            $support = (new CrisisCheck)->support($locale);

            $this->assertNotEmpty($support['resources'], "No resources for {$locale}.");

            foreach ($support['resources'] as $resource) {
                $this->assertNotSame('', trim($resource['contact']), "A resource with no way to reach it, in {$locale}.");
            }

            $text = $support['heading'].' '.implode(' ', $support['body']);

            $this->assertSame(
                [],
                RedTeamLint::blocking(RedTeamLint::inspect($text)),
                "The crisis message breaks the lint in {$locale}.",
            );
        }
    }

    /**
     * It never says what is wrong with them. The product is not qualified to,
     * and a sixteen-year-old told by software that they are depressed has been
     * given a label by something that cannot take it back.
     */
    #[Test]
    public function the_support_message_never_diagnoses(): void
    {
        foreach (ConversationLocale::supported() as $locale) {
            $support = (new CrisisCheck)->support($locale);
            $text = mb_strtolower($support['heading'].' '.implode(' ', $support['body']));

            foreach (['depress', 'depres', 'diagnos', 'disorder', 'mental illness', 'suicidal', 'suicida'] as $clinical) {
                $this->assertStringNotContainsString($clinical, $text, "Clinical language in {$locale}.");
            }
        }
    }

    /**
     * The assessment is the other place a student says something that cannot
     * wait. The answer is still saved — refusing to store it throws away what
     * they wrote — and the support block travels back with the save, so it
     * reaches them on the question rather than in an analysis twenty minutes
     * later.
     */
    #[Test]
    public function an_answer_that_points_to_crisis_is_saved_and_answered_with_support(): void
    {
        $student = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'What is hard right now?',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($student)->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            ['question_id' => $question->id, 'response_text' => 'Some days I want to die.'],
        );

        $response->assertOk();
        $response->assertJsonStructure(['id', 'support' => ['heading', 'body', 'resources']]);

        $this->assertSame(1, Answer::query()->where('assessment_id', $assessment->id)->count());
    }

    /**
     * And an ordinary answer carries nothing extra — the block appears when it
     * is needed and never as furniture.
     */
    #[Test]
    public function an_ordinary_answer_carries_no_support_block(): void
    {
        $student = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'What is hard right now?',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($student)->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            ['question_id' => $question->id, 'response_text' => 'Chemistry is hard but I like the labs.'],
        );

        $response->assertOk();
        $response->assertJsonMissingPath('support');
    }
}
