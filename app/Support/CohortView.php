<?php

namespace App\Support;

use App\Enums\CohortSignal;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Gap;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What a counsellor, pastor or programme director sees about their cohort.
 *
 * The question this surface answers is **"who needs a person this week"**, not
 * "who is doing well". That is the whole design. A school dashboard that ranks
 * students by readiness turns a tool built to remove decision friction into a
 * tool that adds social friction, and the ranking would be the most-used
 * feature on the page by a distance.
 *
 * Three rules hold it to that:
 *
 * 1. **Every student lands in exactly one bucket.** A roster where one name
 *    appears under four headings is a list nobody reads, and the first
 *    matching signal is the binding constraint anyway.
 * 2. **Nobody carries a number.** No score, no percentage, no readiness level,
 *    no position. The counts on this page are all counts of the *cohort*,
 *    which is the reporting a school actually bought.
 * 3. **Nothing the student said reaches it.** Staff see that a gap exists and
 *    what kind it is; they never see the sentence it was found in. The
 *    boundary list is {@see ParentVisibility::FORBIDDEN_KEYS}, reused rather
 *    than copied — a second copy of a privacy rule is the copy that drifts.
 */
class CohortView
{
    /**
     * How long the same next step may sit before it is worth a conversation.
     *
     * Two weeks, because an action is meant to be a thing you could do this
     * week. Shorter would flag a student for having a normal busy fortnight,
     * which teaches the counsellor to ignore the page.
     */
    public const STALLED_AFTER_DAYS = 14;

    /**
     * The gap types a school is actually positioned to remove.
     *
     * A counsellor can arrange a visit and can find money. They cannot fix a
     * student's outlook on their own future by being told about it, and
     * surfacing that one to staff turns a private admission into a file note.
     *
     * @var list<GapType>
     */
    public const ACTIONABLE_GAPS = [GapType::Access, GapType::Finances];

    /**
     * @return array{
     *     seats: array{used: int, pending: int, limit: int},
     *     counts: array<string, int>,
     *     groups: list<array{key: string, label: string, move: string, needs_somebody: bool, students: list<array{id: string, name: string, detail: ?string}>}>
     * }
     */
    public function for(Organization $organization): array
    {
        $students = $organization->users()
            ->wherePivot('role', 'member')
            ->with(['actions', 'gaps', 'parentConsents'])
            ->get();

        $started = $organization->assessments()
            ->where('status', 'completed')
            ->pluck('user_id')
            ->filter()
            ->unique();

        $grouped = [];

        foreach ($students as $student) {
            $signal = $this->signalFor($student, $started->contains($student->id));

            $grouped[$signal->value][] = [
                'id' => $student->id,
                'name' => $student->name,
                'detail' => $this->detailFor($student, $signal),
            ];
        }

        return [
            'seats' => [
                'used' => $organization->users()->count(),
                'pending' => $organization->invitations()->whereNull('accepted_at')->count(),
                'limit' => $organization->memberLimit(),
            ],
            'counts' => [
                'students' => $students->count(),
                'started' => $started->count(),
                'steps_finished' => $students->sum(
                    fn (User $student) => $student->actions->where('status.value', 'completed')->count()
                ),
                'obstacles_cleared' => $students->sum(
                    fn (User $student) => $student->gaps->reject(fn (Gap $gap) => $gap->isActive())->count()
                ),
            ],
            'groups' => collect(CohortSignal::ordered())
                ->map(fn (CohortSignal $signal) => [
                    'key' => $signal->value,
                    'label' => $signal->label(),
                    'move' => (new ConversationPrompts)->forMentor($signal),
                    'needs_somebody' => $signal->needsSomebody(),
                    'students' => $grouped[$signal->value] ?? [],
                ])
                ->all(),
        ];
    }

    /**
     * The first signal that applies, which is the one worth acting on.
     */
    protected function signalFor(User $student, bool $hasStarted): CohortSignal
    {
        if (AccessPolicy::requiresParentConsent($student) && ! AccessPolicy::hasParentConsent($student)) {
            return CohortSignal::NeedsConsent;
        }

        if (! $hasStarted) {
            return CohortSignal::NotStarted;
        }

        if ($this->actionableGap($student)) {
            return CohortSignal::Blocked;
        }

        if ($this->stalledSince($student)) {
            return CohortSignal::Stalled;
        }

        return CohortSignal::Moving;
    }

    /**
     * One short phrase, never a sentence the student wrote.
     *
     * The gap's `summary` and `evidence` both stay behind: the summary is the
     * engine describing a teenager to an adult, and the evidence is the
     * teenager's own words. The *kind* of gap is enough to make the call
     * actionable, and it is the most a member of staff needs to pick up a
     * phone.
     */
    protected function detailFor(User $student, CohortSignal $signal): ?string
    {
        return match ($signal) {
            CohortSignal::Blocked => $this->actionableGap($student)?->type->label(),
            CohortSignal::Stalled => $this->stalledSince($student)?->diffForHumans(),
            default => null,
        };
    }

    protected function actionableGap(User $student): ?Gap
    {
        return $student->gaps
            ->filter(fn (Gap $gap) => $gap->isActive())
            ->first(fn (Gap $gap) => in_array($gap->type, self::ACTIONABLE_GAPS, true));
    }

    /**
     * When the student's current step was handed to them, if it has been
     * sitting long enough to be worth asking about.
     */
    protected function stalledSince(User $student): ?Carbon
    {
        $current = $student->actions->first(fn (Action $action) => $action->isActive());

        if (! $current || ! $current->assigned_at) {
            return null;
        }

        return $current->assigned_at->lessThan(now()->subDays(self::STALLED_AFTER_DAYS))
            ? $current->assigned_at
            : null;
    }
}
