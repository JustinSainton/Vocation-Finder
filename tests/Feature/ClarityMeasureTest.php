<?php

namespace Tests\Feature;

use App\Enums\ClarityMoment;
use App\Enums\ClarityShift;
use App\Enums\ClarityStanding;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ClarityCheck;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Models\VocationalProfile;
use App\Support\ClarityMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Roadmap 5.1 — clarity, read at both ends.
 *
 * The blueprint calls this "one of the clearest indicators of usefulness", and
 * the whole measure rests on one property: the first reading was genuinely
 * taken first. Everything asserted here is a way that property could quietly
 * stop being true — a baseline taken afterwards, a baseline rewritten
 * afterwards, or a missing pair counted as no change.
 */
class ClarityMeasureTest extends TestCase
{
    use RefreshDatabase;

    protected function assessment(?User $user = null): Assessment
    {
        return Assessment::create([
            'user_id' => $user?->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    protected function answer(Assessment $assessment): void
    {
        Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => Question::create([
                'category_id' => QuestionCategory::firstOrCreate(
                    ['slug' => 'service'],
                    ['name' => 'Service', 'sort_order' => 1],
                )->id,
                'question_text' => 'What do people come to you for?',
                'sort_order' => 1,
            ])->id,
            'response_text' => 'I like fixing things.',
        ]);
    }

    protected function portrait(Assessment $assessment): void
    {
        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'user_id' => $assessment->user_id,
            'opening_synthesis' => 'You keep going back to the same kind of problem.',
        ]);
    }

    /**
     * The ordinary path: a word before, a word after, and a comparison that
     * happens in code with no model anywhere near it.
     */
    #[Test]
    public function two_readings_are_compared_by_rank_alone(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);

        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before,
            'standing' => ClarityStanding::VagueSense,
        ]);
        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::After,
            'standing' => ClarityStanding::KnowWhatToTry,
        ]);

        $this->assertSame(ClarityShift::Clearer, (new ClarityMovement)->for($assessment->fresh()));
    }

    /**
     * Somebody leaving less clear than they arrived is the finding, not noise.
     * It has its own case and it is flagged for reading.
     */
    #[Test]
    public function leaving_less_clear_is_recorded_as_its_own_answer(): void
    {
        $assessment = $this->assessment();

        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before,
            'standing' => ClarityStanding::FewOptions,
        ]);
        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::After,
            'standing' => ClarityStanding::VagueSense,
        ]);

        $shift = (new ClarityMovement)->for($assessment->fresh());

        $this->assertSame(ClarityShift::LessClear, $shift);
        $this->assertTrue($shift->needsReading());
    }

    /**
     * A missing reading is unmeasured, and never folded into "unchanged".
     * Counting an absent pair as no-change is the most flattering possible
     * error in the one metric singled out as the measure of usefulness, and it
     * would be invisible in every report built on top of it.
     */
    #[Test]
    public function a_missing_reading_is_never_counted_as_no_change(): void
    {
        $assessment = $this->assessment();

        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before,
            'standing' => ClarityStanding::NoIdea,
        ]);

        $this->assertSame(ClarityShift::Unmeasured, (new ClarityMovement)->for($assessment->fresh()));

        $counts = (new ClarityMovement)->across(collect([$assessment->fresh()]));

        $this->assertSame(1, $counts['unmeasured']);
        $this->assertSame(0, $counts['unchanged']);
    }

    /**
     * A "before" is only a before if it was taken before. Once an answer
     * exists the reading is a recollection, and people misremember how lost
     * they were in the direction that flatters whatever happened next.
     */
    #[Test]
    public function a_baseline_cannot_be_taken_after_the_questions_have_started(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);
        $this->answer($assessment);

        $this->actingAs($student)
            ->post("/assessment/{$assessment->id}/clarity", [
                'moment' => ClarityMoment::Before->value,
                'standing' => ClarityStanding::NoIdea->value,
            ])
            ->assertSessionHasErrors('moment');

        $this->assertSame(0, ClarityCheck::count());
    }

    /**
     * And the second reading cannot be taken before there is anything to have
     * become clearer about.
     */
    #[Test]
    public function the_second_reading_cannot_be_taken_before_the_portrait_exists(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);

        $this->actingAs($student)
            ->post("/assessment/{$assessment->id}/clarity", [
                'moment' => ClarityMoment::After->value,
                'standing' => ClarityStanding::KnowWhatToTry->value,
            ])
            ->assertSessionHasErrors('moment');

        $this->portrait($assessment);

        $this->actingAs($student)
            ->post("/assessment/{$assessment->id}/clarity", [
                'moment' => ClarityMoment::After->value,
                'standing' => ClarityStanding::KnowWhatToTry->value,
            ])
            ->assertRedirect();

        $this->assertSame(1, ClarityCheck::count());
    }

    /**
     * The first reading stands. A student who answers, reads their portrait
     * and then revises the baseline has let the result edit the thing it is
     * being measured against.
     */
    #[Test]
    public function a_second_answer_at_the_same_moment_does_not_replace_the_first(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);

        $post = fn (string $standing) => $this->actingAs($student)
            ->post("/assessment/{$assessment->id}/clarity", [
                'moment' => ClarityMoment::Before->value,
                'standing' => $standing,
            ]);

        $post(ClarityStanding::NoIdea->value)->assertRedirect();
        $post(ClarityStanding::KnowWhatToTry->value)->assertRedirect();

        $this->assertSame(1, ClarityCheck::count());
        $this->assertSame(ClarityStanding::NoIdea, ClarityCheck::first()->standing);
    }

    /**
     * And it cannot be rewritten by anything else either. Append-only, like
     * every other record of what somebody said at the time.
     */
    #[Test]
    public function a_reading_cannot_be_edited_or_deleted(): void
    {
        $assessment = $this->assessment();
        $check = ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before,
            'standing' => ClarityStanding::NoIdea,
        ]);

        $this->expectException(RuntimeException::class);
        $check->update(['standing' => ClarityStanding::KnowWhatToTry]);
    }

    /**
     * Guests take the whole assessment without an account, so they can answer
     * this with the token their assessment issued them — and cannot without it.
     */
    #[Test]
    public function a_guest_answers_with_their_token_and_not_without_it(): void
    {
        $assessment = $this->assessment();
        $assessment->update(['guest_token' => 'a-real-token']);

        $this->post("/assessment/{$assessment->id}/clarity", [
            'moment' => ClarityMoment::Before->value,
            'standing' => ClarityStanding::VagueSense->value,
            'guest_token' => 'a-guess',
        ])->assertForbidden();

        $this->post("/assessment/{$assessment->id}/clarity", [
            'moment' => ClarityMoment::Before->value,
            'standing' => ClarityStanding::VagueSense->value,
            'guest_token' => 'a-real-token',
        ])->assertRedirect();

        $this->assertSame(1, ClarityCheck::count());
    }

    /**
     * Nothing about the comparison reaches the student. Being told you became
     * clearer is being graded on your own feelings by the thing that was
     * supposed to help you, and being told you got less clear is worse.
     */
    #[Test]
    public function what_the_two_readings_add_up_to_never_reaches_the_student(): void
    {
        $student = User::factory()->create();
        $assessment = $this->assessment($student);
        $this->portrait($assessment);

        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before,
            'standing' => ClarityStanding::NoIdea,
        ]);
        ClarityCheck::create([
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::After,
            'standing' => ClarityStanding::KnowWhatToTry,
        ]);

        $props = $this->actingAs($student)
            ->get("/assessment/{$assessment->id}/results")
            ->assertOk()
            ->viewData('page')['props'];

        $encoded = json_encode($props);

        foreach (ClarityShift::values() as $shift) {
            $this->assertStringNotContainsString($shift, $encoded);
        }

        $this->assertStringNotContainsString('clarity', strtolower($encoded));
    }
}
