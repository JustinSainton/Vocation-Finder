<?php

namespace Tests\Feature;

use App\Enums\EvaluationOutcome;
use App\Enums\FeedbackQuestion;
use App\Enums\FeedbackStanding;
use App\Models\Assessment;
use App\Models\EvaluationFeedback;
use App\Models\EvaluationLog;
use App\Models\User;
use App\Models\VocationalCategory;
use App\Support\EngineVersion;
use Database\Seeders\VocationalCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Roadmap 0.4 and 0.5 — making the engine measurable.
 *
 * The MVP exit test is "pick 20 real juniors and count how many finish with
 * one action they can describe, and how many come back." Neither number is
 * answerable without these two tables, so this is the gate on the exit test
 * rather than a nicety behind it.
 */
class EvaluationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function assessment(?User $user = null): Assessment
    {
        return Assessment::create([
            'user_id' => $user?->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);
    }

    #[Test]
    public function an_engine_run_is_append_only(): void
    {
        $log = EvaluationLog::create([
            'assessment_id' => $this->assessment()->id,
            'outcome' => EvaluationOutcome::Completed,
        ]);

        $this->expectException(RuntimeException::class);

        $log->update(['outcome' => EvaluationOutcome::Failed]);
    }

    #[Test]
    public function an_engine_run_cannot_be_deleted(): void
    {
        $log = EvaluationLog::create([
            'assessment_id' => $this->assessment()->id,
            'outcome' => EvaluationOutcome::Failed,
        ]);

        $this->expectException(RuntimeException::class);

        $log->delete();
    }

    /**
     * The log outlives what it describes. A run that failed is exactly the run
     * whose assessment is most likely to be cleaned up later.
     */
    #[Test]
    public function a_log_survives_the_assessment_it_describes(): void
    {
        $assessment = $this->assessment();

        $log = EvaluationLog::create([
            'assessment_id' => $assessment->id,
            'outcome' => EvaluationOutcome::Failed,
            'failure_reason' => 'Narrative failed the red-team pass: clinical',
        ]);

        $assessment->forceDelete();

        $this->assertNotNull($log->fresh(), 'The log went with the assessment.');
        $this->assertNull($log->fresh()->assessment_id);
    }

    /**
     * Versions are derived from the artefacts themselves, so they cannot drift
     * from what actually ran. A hand-maintained constant is a promise someone
     * has to keep on every edit, and the once it is forgotten is the once the
     * data mattered.
     */
    #[Test]
    public function the_prompt_version_changes_when_a_prompt_changes(): void
    {
        $before = EngineVersion::prompt();

        $path = base_path('app/Support/RedTeamLint.php');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, $original."\n// a prompt-shaping edit\n");

            $this->assertNotSame($before, EngineVersion::prompt());
        } finally {
            file_put_contents($path, $original);
        }

        $this->assertSame($before, EngineVersion::prompt());
    }

    #[Test]
    public function the_taxonomy_version_changes_when_the_taxonomy_changes(): void
    {
        $this->seed(VocationalCategorySeeder::class);

        $before = EngineVersion::taxonomy();

        VocationalCategory::query()->first()->update(['core_function' => 'Something else entirely']);

        $this->assertNotSame($before, EngineVersion::taxonomy());
    }

    /**
     * An empty taxonomy is a legitimate state (a fresh database), and it must
     * not be mistaken for a populated one — otherwise every run against an
     * unseeded environment is filed under the same version as real ones.
     */
    #[Test]
    public function an_empty_taxonomy_has_its_own_version(): void
    {
        $empty = EngineVersion::taxonomy();

        $this->seed(VocationalCategorySeeder::class);

        $this->assertNotSame($empty, EngineVersion::taxonomy());
    }

    #[Test]
    public function every_version_is_reported_together(): void
    {
        $this->assertSame(
            ['model_version', 'prompt_version', 'taxonomy_version'],
            array_keys(EngineVersion::all()),
        );
    }

    #[Test]
    public function a_student_can_say_the_portrait_is_not_them(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);

        $this->actingAs($student)
            ->post("/assessment/{$assessment->id}/feedback", [
                'question' => FeedbackQuestion::SoundsLikeMe->value,
                'standing' => FeedbackStanding::NotAtAll->value,
                'comment' => 'it reads like someone else',
            ])
            ->assertRedirect();

        $feedback = EvaluationFeedback::sole();

        $this->assertSame(FeedbackQuestion::SoundsLikeMe, $feedback->question);
        $this->assertTrue($feedback->standing->isDissent());
        $this->assertSame($student->id, $feedback->user_id);
        $this->assertNotNull($feedback->prompt_version);
    }

    #[Test]
    public function a_student_cannot_rate_someone_elses_result(): void
    {
        $assessment = $this->assessment(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post("/assessment/{$assessment->id}/feedback", [
                'question' => FeedbackQuestion::Useful->value,
                'standing' => FeedbackStanding::Completely->value,
            ])
            ->assertForbidden();

        $this->assertSame(0, EvaluationFeedback::count());
    }

    /**
     * Guests take the whole assessment without an account. Requiring a login
     * to disagree with the result would sample only the students it worked
     * for, which is the population whose opinion we least need.
     */
    #[Test]
    public function a_guest_can_answer_with_the_token_their_assessment_issued(): void
    {
        $assessment = $this->assessment();
        $assessment->update(['guest_token' => 'a-real-token']);

        $this->post("/assessment/{$assessment->id}/feedback", [
            'question' => FeedbackQuestion::ClearNextStep->value,
            'standing' => FeedbackStanding::Mostly->value,
            'guest_token' => 'a-real-token',
        ])->assertRedirect();

        $this->assertSame(1, EvaluationFeedback::count());
    }

    #[Test]
    public function a_guest_without_the_token_cannot_answer(): void
    {
        $assessment = $this->assessment();
        $assessment->update(['guest_token' => 'a-real-token']);

        $this->post("/assessment/{$assessment->id}/feedback", [
            'question' => FeedbackQuestion::ClearNextStep->value,
            'standing' => FeedbackStanding::Mostly->value,
            'guest_token' => 'a-guess',
        ])->assertForbidden();

        $this->assertSame(0, EvaluationFeedback::count());
    }

    /**
     * A first impression is the impression that decides whether they come
     * back, and overwriting it would delete the only record of it.
     */
    #[Test]
    public function a_second_answer_does_not_overwrite_the_first(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);

        foreach ([FeedbackStanding::NotAtAll, FeedbackStanding::Completely] as $standing) {
            $this->actingAs($student)
                ->post("/assessment/{$assessment->id}/feedback", [
                    'question' => FeedbackQuestion::SoundsLikeMe->value,
                    'standing' => $standing->value,
                ])
                // The second answer is absorbed, not rejected. A student who
                // taps twice must not be shown an error for it — the
                // append-only guard on the model is the backstop, not the
                // mechanism.
                ->assertRedirect();
        }

        $this->assertSame(1, EvaluationFeedback::count());
        $this->assertSame(FeedbackStanding::NotAtAll, EvaluationFeedback::sole()->standing);
    }

    /**
     * Nothing numeric is ever shown for a student's own result; the wording is
     * the answer, and `rank()` exists only to order findings internally.
     */
    #[Test]
    public function the_answers_are_words_not_a_scale(): void
    {
        foreach (FeedbackStanding::cases() as $standing) {
            $this->assertDoesNotMatchRegularExpression('/\d/', $standing->label());
        }

        foreach (FeedbackQuestion::cases() as $question) {
            $this->assertDoesNotMatchRegularExpression('/\d/', $question->prompt());
        }
    }

    #[Test]
    public function dissent_is_the_low_half_of_the_answers(): void
    {
        $this->assertTrue(FeedbackStanding::NotAtAll->isDissent());
        $this->assertTrue(FeedbackStanding::Somewhat->isDissent());
        $this->assertFalse(FeedbackStanding::Mostly->isDissent());
        $this->assertFalse(FeedbackStanding::Completely->isDissent());
    }
}
