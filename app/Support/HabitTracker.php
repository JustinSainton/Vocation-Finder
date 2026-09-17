<?php

namespace App\Support;

use App\Enums\HabitCadence;
use App\Enums\HabitStanding;
use App\Enums\HabitStatus;
use App\Models\Gap;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Roadmap 1.5 — habits, and how they are actually going.
 *
 * ## No model call
 *
 * Like readiness, every input here is a date, a boolean or an enum already in
 * the database. Asking a model how a student's habit is going would be asking
 * it to guess at something we can count, and it would guess differently on
 * Tuesday.
 *
 * ## Naming a miss never costs the student
 *
 * A missed occasion the student explained is removed from the denominator
 * rather than counted as a failure. This is the same rule readiness uses when
 * naming an obstacle moves a student *up*, and it exists for the same reason:
 * the honest answer must never be the expensive one. A student who learns
 * that saying "I couldn't, I had practice" lowers their standing learns to
 * stop answering, and silence is the one state the coach can do nothing with.
 *
 * Silence still counts. Not answering is not the same as explaining.
 *
 * ## Why there is no streak
 *
 * A streak's entire mechanic is that breaking it destroys something. Applied
 * to a sixteen-year-old's vocational formation that is a device for producing
 * shame and inaccurate self-reports, and the product needs the accurate report
 * more than it needs adherence.
 */
class HabitTracker
{
    /**
     * Occasions that must have come round before the standing says anything
     * other than "just started". Three is enough to distinguish a habit from
     * a single good day and short enough that a weekly habit is not silent
     * for two months.
     */
    protected const MIN_OCCASIONS = 3;

    protected const PART_OF_THE_WEEK = 2 / 3;

    protected const TAKING_HOLD = 1 / 3;

    /**
     * Prescribe a habit. The gap is required by the signature, not just by
     * the schema, so "not generic" is visible at every call site.
     */
    public function prescribe(
        User $user,
        Gap $gap,
        string $title,
        HabitCadence $cadence,
        ?string $why = null,
    ): Habit {
        if ($gap->user_id !== $user->id) {
            throw new RuntimeException('That gap belongs to someone else.');
        }

        $title = trim($title);

        if ($title === '') {
            throw new RuntimeException('A habit needs to say what the student actually does.');
        }

        /**
         * The same list-detection an action goes through. "Read one article
         * and email two people and update your resume" is a plan, and a plan
         * repeated daily is the decision friction this product exists to
         * remove, arriving on a schedule.
         */
        if (! ActionQueue::isSingleAction($title)) {
            throw new RuntimeException(
                'That is more than one habit. Pick the single repeated thing and say it in one sentence.'
            );
        }

        if ($this->active($user)->contains(fn (Habit $habit) => $this->sameThing($habit->title, $title))) {
            throw new RuntimeException('They are already doing that one. Read their habits before prescribing another.');
        }

        return Habit::create([
            'user_id' => $user->id,
            'gap_id' => $gap->id,
            'status' => HabitStatus::Active,
            'cadence' => $cadence,
            'title' => $title,
            'why' => $why,
            'started_at' => now(),
        ]);
    }

    /**
     * Record one occasion. Idempotent by day: tapping twice restates the same
     * answer rather than adding a second one, because the unique index would
     * otherwise turn a double-tap into an error the student has to read.
     */
    public function checkIn(Habit $habit, bool $happened, ?string $note = null, ?CarbonImmutable $on = null): HabitCheckIn
    {
        $day = ($on ?? CarbonImmutable::now())->startOfDay();

        $existing = $habit->checkIns()->whereDate('observed_on', $day)->first();

        if ($existing) {
            // The answer itself is fixed — the model guard enforces that. A
            // note arriving afterwards is the student saying more about a day
            // they already answered for, which is exactly what we want.
            if (filled($note) && blank($existing->note)) {
                $existing->update(['note' => $note]);
            }

            return $existing;
        }

        return $habit->checkIns()->create([
            'observed_on' => $day,
            'happened' => $happened,
            'note' => $note,
        ]);
    }

    /**
     * @return Collection<int, Habit>
     */
    public function active(User $user): Collection
    {
        return Habit::query()
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('started_at')
            ->get();
    }

    /**
     * How the habit is going, as a word.
     *
     * @return array{standing: HabitStanding, expected: int, kept: int, explained: int}
     */
    public function standing(Habit $habit, ?CarbonImmutable $asOf = null): array
    {
        $to = ($asOf ?? CarbonImmutable::now())->startOfDay();
        $cadence = $habit->cadence;

        $from = CarbonImmutable::parse($habit->started_at)->startOfDay()
            ->max($to->subDays($cadence->windowInDays() - 1));

        $expected = $cadence->occasionsIn($from, $to);

        $checkIns = $habit->checkIns
            ->filter(fn (HabitCheckIn $checkIn) => $checkIn->observed_on->betweenIncluded($from, $to));

        $kept = $checkIns->where('happened', true)->count();
        $explained = $checkIns->filter(fn (HabitCheckIn $checkIn) => $checkIn->isExcused())->count();

        if ($expected < self::MIN_OCCASIONS) {
            return [
                'standing' => HabitStanding::Starting,
                'expected' => $expected,
                'kept' => $kept,
                'explained' => $explained,
            ];
        }

        // Explained misses leave the denominator. Silence does not.
        $judged = max(1, $expected - $explained);
        $ratio = $kept / $judged;

        $standing = match (true) {
            $ratio >= self::PART_OF_THE_WEEK => HabitStanding::PartOfTheWeek,
            $ratio >= self::TAKING_HOLD => HabitStanding::TakingHold,
            default => HabitStanding::Stalled,
        };

        return [
            'standing' => $standing,
            'expected' => $expected,
            'kept' => $kept,
            'explained' => $explained,
        ];
    }

    /**
     * What a student is shown. Words and a move — never the counts, which are
     * here so the coach can reason and the tests can pin, not so a teenager
     * can be handed a score for their week.
     *
     * @return list<array<string, mixed>>
     */
    public function forStudent(User $user, ?CarbonImmutable $asOf = null): array
    {
        return $this->active($user)
            ->map(function (Habit $habit) use ($asOf) {
                $standing = $this->standing($habit, $asOf)['standing'];

                return [
                    'id' => $habit->id,
                    'title' => $habit->title,
                    'why' => $habit->why,
                    'cadence' => $habit->cadence->label(),
                    'standing' => $standing->label(),
                    'meaning' => $standing->meaning(),
                    'next_move' => $standing->nextMove(),
                    'answered_today' => $this->answeredOn($habit, $asOf),
                ];
            })
            ->values()
            ->all();
    }

    public function answeredOn(Habit $habit, ?CarbonImmutable $on = null): bool
    {
        $day = ($on ?? CarbonImmutable::now())->startOfDay();

        return $habit->checkIns()->whereDate('observed_on', $day)->exists();
    }

    /**
     * Crude on purpose, and the same comparison the action queue makes: near
     * duplicates are what a model produces when it has not read the existing
     * habits, and a student with two versions of the same habit has been
     * given a chore list.
     */
    protected function sameThing(string $a, string $b): bool
    {
        $normalise = fn (string $text) => preg_replace('/[^a-z0-9 ]/', '', strtolower(trim($text)));

        return $normalise($a) === $normalise($b);
    }
}
