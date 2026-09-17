<?php

namespace Tests\Feature;

use App\Enums\CohortSignal;
use App\Enums\GapStatus;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Gap;
use App\Models\User;
use App\Support\ConversationPrompts;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sentences an adult reads about a student they love or teach.
 *
 * This is the highest-stakes copy in the product and the lowest-tech: a fixed,
 * reviewed catalogue rather than anything generated. These tests are what make
 * "reviewed" mean something after the review is over.
 */
class ConversationPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function student(string $name = 'Maya Alvarez'): User
    {
        return User::factory()->create(['name' => $name]);
    }

    /**
     * The whole catalogue is held to the same standard as a narrative.
     *
     * A prompt goes to a parent, monthly, unread by anyone here first. If
     * "God has called you to" would be refused inside a student's portrait, it
     * cannot be acceptable in the email home.
     */
    #[Test]
    public function every_prompt_passes_the_red_team_lint(): void
    {
        $refused = [];

        foreach (ConversationPrompts::all() as $prompt) {
            if (! RedTeamLint::passes($prompt)) {
                $refused[$prompt] = RedTeamLint::inspect($prompt);
            }
        }

        $this->assertSame([], $refused, 'A conversation prompt would be refused as a narrative.');
    }

    /**
     * A prompt is a move, not a description. "Your child seems disengaged" is
     * a verdict handed to somebody with no way to act on it.
     */
    #[Test]
    public function every_prompt_asks_the_adult_to_do_something(): void
    {
        $inert = [];

        foreach (ConversationPrompts::all() as $prompt) {
            $isMove = (bool) preg_match(
                '/^(Ask|Offer|Tell|Find|Check|Call|Sit|Take|Let|Nothing)\b/',
                $prompt,
            );

            if (! $isMove) {
                $inert[] = $prompt;
            }
        }

        $this->assertSame([], $inert, 'These prompts describe rather than ask.');
    }

    /**
     * Three placeholders, each admitted for a stated reason:
     *
     * - `:name` — the student's first name, which the parent obviously knows.
     * - `:action` — the step they are on, already on the parent payload as
     *   `current_action`, and the thing that makes an offer of help specific.
     * - `:college` — the name of an institution, which is a public fact about
     *   a place rather than anything about a person.
     * - `:company` — the employer on a public job posting, for the same
     *   reason.
     *
     * Everything else fails, and the point of the list is what is absent from
     * it: a reflection, a gap's evidence, a brain entry. A catalogue able to
     * reach any of those would be the privacy boundary broken in the one
     * payload that leaves the building by email.
     */
    #[Test]
    public function the_only_placeholders_are_ones_the_reader_may_see(): void
    {
        foreach (ConversationPrompts::all() as $prompt) {
            preg_match_all('/:([a-z_]+)/', $prompt, $matches);

            $this->assertSame(
                [],
                array_values(array_diff(array_unique($matches[1]), ['name', 'action', 'college', 'company'])),
                "This prompt carries a placeholder its reader may not see — {$prompt}",
            );

            $this->assertStringNotContainsString('{', $prompt);
        }
    }

    #[Test]
    public function a_parent_is_addressed_with_the_students_first_name(): void
    {
        $student = $this->student('Maya Alvarez');

        $prompts = collect(range(0, 11))->map(fn (int $month) => (new ConversationPrompts)->forParent(
            $student,
            null,
            collect(),
            Carbon::create(2026, 1, 1)->addMonths($month),
        ));

        $this->assertTrue($prompts->contains(fn (string $prompt) => str_contains($prompt, 'Maya')));
        $this->assertFalse($prompts->contains(fn (string $prompt) => str_contains($prompt, 'Alvarez')));
        $this->assertFalse($prompts->contains(fn (string $prompt) => str_contains($prompt, ':name')));
    }

    /**
     * A parent who receives the identical sentence every month stops reading
     * the email, and then the mechanism is gone.
     */
    #[Test]
    public function the_sentence_changes_between_months(): void
    {
        $student = $this->student();

        $seen = collect(range(0, 11))
            ->map(fn (int $month) => (new ConversationPrompts)->forParent(
                $student,
                null,
                collect(),
                Carbon::create(2026, 1, 1)->addMonths($month),
            ))
            ->unique();

        $this->assertGreaterThan(1, $seen->count());
    }

    /**
     * And it does not change under somebody mid-conversation: the page and the
     * email have to agree, and a sentence that moves on reload is a sentence
     * nobody trusts.
     */
    #[Test]
    public function the_sentence_holds_still_within_a_month(): void
    {
        $student = $this->student();
        $asOf = Carbon::create(2026, 3, 14);

        /*
         * Read many times, not twice. The bank has four entries, so a single
         * comparison against a sentence that re-rolled on every read would
         * coincide one time in four and the test would pass by luck — which
         * it did, until a probe re-rolled the period and failed to bite.
         */
        $readings = collect(range(0, 15))
            ->map(fn (int $day) => (new ConversationPrompts)->forParent(
                $student,
                null,
                collect(),
                $asOf->copy()->addDays($day),
            ))
            ->unique();

        $this->assertCount(1, $readings);
    }

    /**
     * Two families in the same month should not be reading the same line, or
     * the first two parents who compare notes learn the sentence was never
     * about their kid.
     */
    #[Test]
    public function two_students_are_not_handed_the_same_sentence(): void
    {
        $asOf = Carbon::create(2026, 5, 1);

        $seen = collect(range(1, 20))
            ->map(fn (int $index) => (new ConversationPrompts)->forParent(
                $this->student("Student {$index}"),
                null,
                collect(),
                $asOf,
            ))
            ->unique();

        $this->assertGreaterThan(1, $seen->count());
    }

    #[Test]
    public function a_student_with_a_step_in_progress_gets_a_prompt_about_arranging_it(): void
    {
        $student = $this->student();

        $current = Action::create([
            'user_id' => $student->id,
            'title' => 'Ask your aunt about the ICU.',
            'assigned_at' => now(),
        ]);

        $prompt = (new ConversationPrompts)->forParent($student, $current, collect());

        /*
         * The step itself is named. A parent who knows their kid is going to
         * ask their aunt about the ICU can offer to drive them; a parent told
         * only that "something is in progress" can offer nothing.
         */
        $this->assertStringContainsString('Ask your aunt about the ICU', $prompt);
        $this->assertStringNotContainsString(':action', $prompt);
    }

    /**
     * The gap's own closing move stays in the bank, so the catalogue extends
     * `GapType::closingMove()` rather than quietly replacing it.
     */
    #[Test]
    public function an_open_gap_still_offers_its_own_closing_move(): void
    {
        /*
         * A fixed id, because the prompt is seeded on it. With a random uuid
         * this test drew twelve hashes from a four-entry bank and missed the
         * closing move about one run in thirty — it failed once in a full
         * suite pass having passed six times in isolation. A sweep of a hash
         * is not a sample; pin the seed and the sweep is exhaustive.
         */
        $student = $this->student();
        $student->forceFill(['id' => '01920000-0000-7000-8000-000000000001'])->save();

        $gap = Gap::create([
            'user_id' => $student->id,
            'type' => GapType::Finances,
            'status' => GapStatus::Open,
            'summary' => 'Cannot pay the application fees.',
            'source' => 'assessment',
        ]);

        $seen = collect(range(0, 35))
            ->map(fn (int $month) => (new ConversationPrompts)->forParent(
                $student,
                null,
                collect([$gap]),
                Carbon::create(2026, 1, 1)->addMonths($month),
            ))
            ->unique();

        $this->assertContains(GapType::Finances->closingMove(), $seen->all());
    }

    /**
     * A cohort heading sits above a list of names. One that named a student
     * would be describing that person to everybody else in the room.
     */
    #[Test]
    public function a_cohort_heading_never_names_a_student(): void
    {
        foreach (ConversationPrompts::MENTOR as $bank) {
            foreach ($bank as $prompt) {
                $this->assertStringNotContainsString(':name', $prompt);
            }
        }
    }

    #[Test]
    public function every_signal_has_something_to_say(): void
    {
        foreach (CohortSignal::ordered() as $signal) {
            $prompt = (new ConversationPrompts)->forMentor($signal);

            $this->assertNotEmpty($prompt, "{$signal->value} has no move.");
        }
    }

    #[Test]
    public function a_counsellor_is_not_reading_the_same_line_every_month(): void
    {
        $seen = collect(range(0, 11))
            ->map(fn (int $month) => (new ConversationPrompts)->forMentor(
                CohortSignal::Stalled,
                Carbon::create(2026, 1, 1)->addMonths($month),
            ))
            ->unique();

        $this->assertGreaterThan(1, $seen->count());
    }
}
