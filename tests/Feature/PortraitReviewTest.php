<?php

namespace Tests\Feature;

use App\Enums\FeedbackQuestion;
use App\Enums\FeedbackStanding;
use App\Enums\ReviewDimension;
use App\Enums\ReviewStanding;
use App\Models\Assessment;
use App\Models\EvaluationFeedback;
use App\Models\PortraitReview;
use App\Models\User;
use App\Models\VocationalProfile;
use App\Support\ReviewQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Roadmap 5.3 — human review, and the ways a review programme stops being a
 * measurement.
 *
 * The nine dimensions are the easy part. Which portraits get read is where
 * this quietly turns into a highlight reel.
 */
class PortraitReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function portrait(string $opening = 'You keep coming back to the same problem.'): VocationalProfile
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        return VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => $opening,
            'prompt_version' => 'p-1',
        ]);
    }

    protected function reviewer(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * All nine of the blueprint's dimensions, every one phrased so that a
     * failure reads the same way. A dimension where "yes" is the bad answer
     * gets recorded backwards by a tired reviewer at least once.
     */
    #[Test]
    public function all_nine_dimensions_are_asked_and_none_is_inverted(): void
    {
        $this->assertCount(9, ReviewDimension::cases());

        foreach (ReviewDimension::cases() as $dimension) {
            $this->assertNotSame('', $dimension->question());
            $this->assertStringEndsWith('?', $dimension->question());
        }

        $this->assertStringContainsString('stop short', ReviewDimension::NoOverclaiming->question());
    }

    /**
     * A reviewer is handed the next portrait. There is no route that opens a
     * chosen one, because a queue people pick from becomes a queue of
     * interesting cases — and the failure this product has is the fluent,
     * plausible, slightly wrong portrait nobody volunteers for.
     */
    #[Test]
    public function a_reviewer_cannot_choose_which_portrait_to_read(): void
    {
        $opensAPortrait = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/reviews'))
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => str_contains($route->uri(), '{'));

        $this->assertCount(0, $opensAPortrait, 'A reviewer must be handed the next portrait, never pick one.');
    }

    /**
     * A student who said "this is not me" has already done the hard part of
     * the review. Leaving that portrait at the back of a chronological queue
     * throws away the best-evidenced finding available.
     */
    #[Test]
    public function the_portraits_students_objected_to_come_first(): void
    {
        $ordinary = $this->portrait('An older portrait nobody complained about.');
        $ordinary->update(['created_at' => now()->subYear()]);

        $objected = $this->portrait('A newer portrait the student rejected.');

        EvaluationFeedback::create([
            'assessment_id' => $objected->assessment_id,
            'question' => FeedbackQuestion::SoundsLikeMe,
            'standing' => FeedbackStanding::NotAtAll,
        ]);

        $this->assertSame($objected->id, (new ReviewQueue)->next($this->reviewer())?->id);
    }

    /**
     * A reviewer never gets the same portrait twice.
     */
    #[Test]
    public function a_reviewer_does_not_see_the_same_portrait_again(): void
    {
        $reviewer = $this->reviewer();
        $first = $this->portrait();
        $second = $this->portrait();
        $second->update(['created_at' => now()->addMinute()]);

        PortraitReview::create([
            'vocational_profile_id' => $first->id,
            'reviewer_id' => $reviewer->id,
            'dimension' => ReviewDimension::Accuracy,
            'standing' => ReviewStanding::Holds,
        ]);

        $this->assertSame($second->id, (new ReviewQueue)->next($reviewer)?->id);
    }

    /**
     * Two reviewers reading the same portrait is allowed and wanted:
     * disagreement between reviewers is a finding about the dimension, not a
     * scheduling error.
     */
    #[Test]
    public function two_reviewers_may_read_the_same_portrait(): void
    {
        $portrait = $this->portrait();
        $first = $this->reviewer();
        $second = $this->reviewer();

        PortraitReview::create([
            'vocational_profile_id' => $portrait->id,
            'reviewer_id' => $first->id,
            'dimension' => ReviewDimension::Accuracy,
            'standing' => ReviewStanding::Holds,
        ]);

        $this->assertSame($portrait->id, (new ReviewQueue)->next($second)?->id);
    }

    /**
     * The reviewer sees what the student wrote. Most of the nine questions —
     * "could this have been written about somebody else" above all — cannot be
     * answered from the portrait alone.
     */
    #[Test]
    public function the_students_own_answers_are_shown_beside_the_portrait(): void
    {
        $this->portrait();

        $props = $this->actingAs($this->reviewer())
            ->get('/admin/reviews')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertArrayHasKey('answers', $props['portrait']);
        $this->assertCount(9, $props['dimensions']);
    }

    /**
     * A judgement is recorded once and never rewritten, and the prompt version
     * is stamped from the portrait — otherwise a regenerated portrait drags
     * every historical criticism onto whichever prompt is current, and the
     * prompt that earned it looks clean.
     */
    #[Test]
    public function a_review_is_written_once_and_stamped_with_the_prompt_it_judged(): void
    {
        $portrait = $this->portrait();
        $reviewer = $this->reviewer();

        $post = fn (string $standing) => $this->actingAs($reviewer)->post("/admin/reviews/{$portrait->id}", [
            'dimension' => ReviewDimension::Specificity->value,
            'standing' => $standing,
        ]);

        $post(ReviewStanding::Fails->value)->assertRedirect();
        $post(ReviewStanding::Holds->value)->assertRedirect();

        $review = PortraitReview::sole();

        $this->assertSame(ReviewStanding::Fails, $review->standing);
        $this->assertSame('p-1', $review->prompt_version);

        $this->expectException(RuntimeException::class);
        $review->update(['standing' => ReviewStanding::Holds]);
    }

    /**
     * Coverage is reported per dimension and never totalled. Eight dimensions
     * holding does not cancel one failing, and a composite score is a thing
     * people optimise instead of read.
     */
    #[Test]
    public function coverage_is_reported_per_dimension_and_never_totalled(): void
    {
        $portrait = $this->portrait();
        $reviewer = $this->reviewer();

        PortraitReview::create([
            'vocational_profile_id' => $portrait->id,
            'reviewer_id' => $reviewer->id,
            'dimension' => ReviewDimension::PsychologicalSafety,
            'standing' => ReviewStanding::Fails,
        ]);

        $coverage = (new ReviewQueue)->coverage();

        $this->assertSame(1, $coverage['portraits']);
        $this->assertSame(1, $coverage['reviewed']);
        $this->assertSame(1, $coverage['failures']['psychological_safety']);
        $this->assertSame(0, $coverage['failures']['accuracy']);
        $this->assertArrayNotHasKey('score', $coverage);
    }

    /**
     * The bench is staff-only. A student handed a review of their own portrait
     * is being shown somebody else's argument about whether they were read
     * correctly.
     */
    #[Test]
    public function the_review_bench_is_not_a_student_facing_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/admin/reviews')
            ->assertForbidden();
    }
}
