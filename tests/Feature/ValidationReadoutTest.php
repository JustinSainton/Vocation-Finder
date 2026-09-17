<?php

namespace Tests\Feature;

use App\Enums\ActionStatus;
use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use App\Enums\FeedbackQuestion;
use App\Enums\FeedbackStanding;
use App\Models\Action;
use App\Models\Assessment;
use App\Models\ClarityCheck;
use App\Models\EvaluationFeedback;
use App\Models\User;
use App\Support\ValidationReadout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 5.2 — the read-out, and the ways it could flatter us.
 *
 * Every assertion here is about a number this class refuses to print. The
 * pilot is 50–100 people, which means the window in which this product is most
 * likely to overclaim about itself is the window it is first shown to anybody.
 */
class ValidationReadoutTest extends TestCase
{
    use RefreshDatabase;

    protected function measure(array $readout, string $key): array
    {
        return collect($readout['measures'])->firstWhere('key', $key);
    }

    protected function assessment(string $status = 'completed'): Assessment
    {
        return Assessment::create([
            'mode' => 'written',
            'status' => $status,
            'started_at' => now(),
        ]);
    }

    protected function feedback(FeedbackQuestion $question, FeedbackStanding $standing): void
    {
        EvaluationFeedback::create([
            'assessment_id' => $this->assessment()->id,
            'question' => $question,
            'standing' => $standing,
        ]);
    }

    /**
     * Eleven students and eight yeses is "73% perceived accuracy" in a deck
     * and nothing at all in reality. Below the threshold the rate is null and
     * the denominator is printed in its place.
     */
    #[Test]
    public function a_sample_too_small_for_a_rate_does_not_get_one(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->feedback(FeedbackQuestion::SoundsLikeMe, FeedbackStanding::Mostly);
        }

        $measure = $this->measure((new ValidationReadout)->for(), 'sounds_like_me');

        $this->assertSame(11, $measure['count']);
        $this->assertSame(11, $measure['of']);
        $this->assertNull($measure['rate']);
        $this->assertStringContainsString('30', (string) $measure['why_no_rate']);
    }

    /**
     * And above it, the rate appears and the caveat disappears.
     */
    #[Test]
    public function a_sample_that_can_carry_a_rate_reports_one(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->feedback(
                FeedbackQuestion::SoundsLikeMe,
                $i < 24 ? FeedbackStanding::Mostly : FeedbackStanding::NotAtAll,
            );
        }

        $measure = $this->measure((new ValidationReadout)->for(), 'sounds_like_me');

        $this->assertSame(0.8, $measure['rate']);
        $this->assertNull($measure['why_no_rate']);
    }

    /**
     * Disagreement is a measure in its own right, not the leftover of
     * satisfaction, and it is marked as something to go and read. An average
     * buries exactly this; that is what averages are for.
     */
    #[Test]
    public function disagreement_is_counted_on_its_own_and_flagged_for_reading(): void
    {
        $this->feedback(FeedbackQuestion::SoundsLikeMe, FeedbackStanding::NotAtAll);
        $this->feedback(FeedbackQuestion::SoundsLikeMe, FeedbackStanding::Completely);

        $dissent = $this->measure((new ValidationReadout)->for(), 'sounds_like_me_dissent');

        $this->assertSame(1, $dissent['count']);
        $this->assertSame(2, $dissent['of']);
        $this->assertTrue($dissent['read_these']);
    }

    /**
     * A clarity pair that was never completed is not a student who did not
     * change. Folding the unmeasured into "unchanged" is the most flattering
     * available error in the measure the blueprint singles out, so the
     * denominator counts only real pairs and the missing ones are printed.
     */
    #[Test]
    public function unmeasured_clarity_is_excluded_from_the_denominator_and_shown(): void
    {
        $moved = $this->assessment();
        ClarityCheck::create(['assessment_id' => $moved->id, 'moment' => ClarityMoment::Before, 'standing' => ClarityStanding::NoIdea]);
        ClarityCheck::create(['assessment_id' => $moved->id, 'moment' => ClarityMoment::After, 'standing' => ClarityStanding::FewOptions]);

        $half = $this->assessment();
        ClarityCheck::create(['assessment_id' => $half->id, 'moment' => ClarityMoment::Before, 'standing' => ClarityStanding::NoIdea]);

        $this->assessment();

        $measure = $this->measure((new ValidationReadout)->for(), 'clarity_improved');

        $this->assertSame(1, $measure['count']);
        $this->assertSame(1, $measure['of']);
        $this->assertSame(2, $measure['unmeasured']);
    }

    /**
     * A student who was never given a step cannot have failed to take one.
     * Counting them in the denominator turns a gap in the product into a
     * verdict on them.
     */
    #[Test]
    public function the_next_step_rate_counts_steps_given_not_students_enrolled(): void
    {
        User::factory()->count(5)->create();
        $student = User::factory()->create();

        Action::create([
            'user_id' => $student->id,
            'status' => ActionStatus::Completed,
            'title' => 'Email the respiratory therapist.',
            'assigned_at' => now(),
        ]);

        $measure = $this->measure((new ValidationReadout)->for(), 'next_step_taken');

        $this->assertSame(1, $measure['count']);
        $this->assertSame(1, $measure['of']);
    }

    /**
     * Bias testing is required, and guessing at who somebody is in order to
     * perform it would create the record we declined to collect — and assign
     * students to groups they are not in.
     */
    #[Test]
    public function the_readout_refuses_to_segment_on_anything_we_would_have_to_infer(): void
    {
        $readout = new ValidationReadout;

        foreach (['gender', 'ethnicity', 'neurodiversity', 'writing_style'] as $attribute) {
            try {
                $readout->segmentKeys($attribute);
                $this->fail("Segmenting by [{$attribute}] should be refused.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($attribute, $exception->getMessage());
            }
        }
    }

    /**
     * What students did tell us, we may split by.
     */
    #[Test]
    public function the_readout_segments_on_what_students_told_us(): void
    {
        User::factory()->create(['grade_level' => 11]);
        User::factory()->create(['grade_level' => 12]);

        $keys = (new ValidationReadout)->segmentKeys('grade_level');

        sort($keys);
        $this->assertSame(['11', '12'], $keys);
    }

    /**
     * These numbers are about whether the product works. A student shown them
     * has been handed the company's self-assessment in place of their result.
     */
    #[Test]
    public function the_readout_is_not_a_student_facing_page(): void
    {
        $student = User::factory()->create(['role' => 'user']);
        $this->actingAs($student)->get('/admin/validation')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/validation')->assertOk();
    }
}
