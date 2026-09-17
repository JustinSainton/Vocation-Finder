<?php

namespace App\Support;

use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The plan: the student's life in sections, with milestones living inside it.
 *
 * Three rules are enforced here rather than in the page that draws it, because
 * a rule that lives in a component is a rule the next component does not have.
 *
 * 1. **The plan is not a to-do list.** It carries exactly one action — the
 *    same one the coach shows, from {@see ActionQueue::current()} — and
 *    milestones carry no "do it" affordance at all. A wide view of twenty
 *    things to start is the decision friction this product exists to remove.
 * 2. **Nothing is counted.** No totals, no completed-of-total, no percentage
 *    and no bar. A plan that scores itself becomes a report card, and a report
 *    card is read by a parent rather than used by a student.
 * 3. **A closed window is a fact, not a verdict.** It is computed from the
 *    date every time this runs, never stored, and it always arrives with a
 *    move attached. There is no "missed" status for the same reason there is
 *    no streak on a habit.
 */
class StudentPlan
{
    /**
     * @return array{sections: list<array<string, mixed>>, action: array<string, mixed>|null, horizon: string}
     */
    public function for(User $user, ?CarbonImmutable $asOf = null): array
    {
        $today = $asOf ?? CarbonImmutable::now();
        $sections = (new PlanSections)->for($user, $today);
        $entries = $this->entries($user, $sections, $today);

        return [
            'horizon' => $this->horizon($sections),
            'sections' => array_map(
                fn (array $section) => [
                    ...$section,
                    'milestones' => array_values(array_filter(
                        $entries,
                        fn (array $entry) => $entry['section'] === $section['key'],
                    )),
                ],
                $sections,
            ),
            /**
             * One, or none. Deliberately the same object the coach surfaces,
             * not a second queue with its own idea of what is next.
             */
            'action' => (new ActionQueue)->current($user)?->only(['id', 'title', 'rationale']),
        ];
    }

    /**
     * Stored milestones and computed passages, merged and dated.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    protected function entries(User $user, array $sections, CarbonImmutable $today): array
    {
        $entries = [
            ...$this->passages($sections, $today),
            ...$user->milestones()
                ->orderBy('due_on')
                ->get()
                ->map(fn (Milestone $milestone) => $this->describe($milestone, $today))
                ->all(),
        ];

        usort($entries, fn (array $a, array $b) => [$a['due_on'], $a['title']] <=> [$b['due_on'], $b['title']]);

        return array_map(
            fn (array $entry) => [...$entry, 'section' => $this->sectionFor($entry['due_on'], $sections)],
            $entries,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(Milestone $milestone, CarbonImmutable $today): array
    {
        $passed = ! $milestone->status->isSettled()
            && $milestone->due_on->lessThan($today->startOfDay());

        return [
            'id' => $milestone->id,
            'kind' => $milestone->kind->label(),
            'title' => $milestone->title,
            'why' => $milestone->why,
            'due_on' => $milestone->due_on->toDateString(),
            'status' => $milestone->status->label(),
            'settled' => $milestone->status->isSettled(),
            'is_yours_to_do' => $milestone->kind->isAchieved(),
            /*
             * Said once, the same way, whatever the milestone was. A date
             * that has gone by is information the student needs in order to
             * decide something; it is not a thing to feel about.
             */
            'window' => $passed
                ? 'That date has gone by. Move it, or set it down on purpose.'
                : null,
        ];
    }

    /**
     * The things that happen whether or not anybody acts.
     *
     * Computed from the sections rather than seeded, so a student cannot end
     * up with a "finish sophomore year" row they have to tick, and cannot be
     * behind on one either.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    protected function passages(array $sections, CarbonImmutable $today): array
    {
        $passages = [];

        foreach ($sections as $section) {
            if (! str_starts_with($section['key'], 'grade-') || $section['ends_on'] === null) {
                continue;
            }

            $grade = (int) substr($section['key'], strlen('grade-'));
            $done = CarbonImmutable::parse($section['ends_on'])->lessThan($today->startOfDay());

            $passages[] = [
                'id' => null,
                'kind' => MilestoneKind::Passage->label(),
                'title' => $grade === 12
                    ? 'Graduate high school'
                    : 'Finish '.lcfirst(PlanSections::gradeNames()[$grade]),
                'why' => null,
                'due_on' => $section['ends_on'],
                'status' => ($done ? MilestoneStatus::Done : MilestoneStatus::NotYet)->label(),
                'settled' => $done,
                'is_yours_to_do' => false,
                'window' => null,
            ];
        }

        return $passages;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    protected function sectionFor(string $dueOn, array $sections): string
    {
        $due = CarbonImmutable::parse($dueOn);
        $landed = null;

        /*
         * The last section that has already opened by this date. Sections are
         * contiguous by construction, so a start date is the only boundary
         * worth testing — checking the end date too looked like belt and
         * braces and was in fact unreachable code, which a mutation probe
         * found by failing to change anything when it was removed.
         *
         * A date before the plan begins lands in the first section rather
         * than being dropped, because a deadline that has just gone by is the
         * one it is most urgent to see.
         */
        foreach ($sections as $section) {
            if ($due->lessThan(CarbonImmutable::parse($section['starts_on']))) {
                break;
            }

            $landed = $section['key'];
        }

        return $landed ?? $sections[0]['key'];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    protected function horizon(array $sections): string
    {
        $named = array_values(array_filter($sections, fn (array $s) => $s['ends_on'] !== null));

        if ($named === []) {
            return 'This is as far ahead as we can see from here, and that is enough to start.';
        }

        return 'From here to '.end($named)['label'].', in chunks, with what each chunk asks of you.';
    }
}
