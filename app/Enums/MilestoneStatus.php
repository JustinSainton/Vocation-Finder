<?php

namespace App\Enums;

use App\Support\StudentPlan;

/**
 * Where a milestone stands.
 *
 * There is deliberately no "missed" and no "failed". A window that has closed
 * is a fact about the calendar, computed from the date rather than stored as a
 * verdict on the student — see {@see StudentPlan}. Storing it as
 * a status would turn the plan into a record of everything they did not do,
 * which is the exact shape of the thing the coach exists to unstick.
 */
enum MilestoneStatus: string
{
    case NotYet = 'not_yet';
    case Underway = 'underway';
    case Done = 'done';
    case PutDown = 'put_down';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::NotYet => 'Not started',
            self::Underway => 'Underway',
            self::Done => 'Done',
            self::PutDown => 'Set down',
        };
    }

    /**
     * Settled milestones are not chased, and a passed window on one is not
     * remarked upon. Setting something down on purpose is a decision, not a
     * lapse — the same rule that lets a student retire a habit.
     */
    public function isSettled(): bool
    {
        return $this === self::Done || $this === self::PutDown;
    }
}
