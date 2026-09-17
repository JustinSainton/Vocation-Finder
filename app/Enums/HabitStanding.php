<?php

namespace App\Enums;

/**
 * How a habit is actually going, as a word.
 *
 * Never a streak, a percentage or a ring. A streak's whole mechanic is that
 * breaking it destroys something, which teaches a student that the honest
 * answer — "I did not do it" — is the expensive one. The product needs the
 * honest answer more than it needs adherence, for the same reason readiness
 * moves *up* when a student names an obstacle.
 */
enum HabitStanding: string
{
    case Starting = 'starting';
    case TakingHold = 'taking_hold';
    case PartOfTheWeek = 'part_of_the_week';
    case Stalled = 'stalled';

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
            self::Starting => 'Just started',
            self::TakingHold => 'Taking hold',
            self::PartOfTheWeek => 'Part of your week',
            self::Stalled => 'Not happening right now',
        };
    }

    public function meaning(): string
    {
        return match ($this) {
            self::Starting => 'There is not enough here yet to say how it is going.',
            self::TakingHold => 'It happens some of the time. That is what the beginning of a habit looks like.',
            self::PartOfTheWeek => 'It happens most of the time you expect it to.',
            self::Stalled => 'It has not been happening. That is information about the habit, not about you.',
        };
    }

    /**
     * Exactly one move, and for a stalled habit it is to change the habit
     * rather than to try harder. A habit a student keeps failing is usually
     * the wrong size, and telling them to recommit is how the tool starts
     * doing the thing it exists to stop.
     */
    public function nextMove(): string
    {
        return match ($this) {
            self::Starting => 'Keep going long enough to see whether it fits.',
            self::TakingHold => 'Notice which days it happens on. Those are the ones to build around.',
            self::PartOfTheWeek => 'Leave it alone. It is working.',
            self::Stalled => 'Make it smaller, move it to a different part of the day, or set it down and pick a different one.',
        };
    }

    public function isSettling(): bool
    {
        return in_array($this, [self::TakingHold, self::PartOfTheWeek], true);
    }
}
