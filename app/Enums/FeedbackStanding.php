<?php

namespace App\Enums;

/**
 * A worded answer, never a number.
 *
 * The student is rating us, not being rated, so a five-point scale would not
 * break the design rules outright. It is still wrong. A scale produces an
 * average, an average produces a dial, and a dial is the habit §10.4 exists to
 * break. Words are also the better measurement: "this is not me" and "some of
 * this is me" are different claims about us, where 2 and 3 are the same claim
 * with different confidence.
 *
 * {@see self::rank()} exists for ordering findings internally. It is never
 * shown, averaged, or sent to a student.
 */
enum FeedbackStanding: string
{
    case NotAtAll = 'not_at_all';
    case Somewhat = 'somewhat';
    case Mostly = 'mostly';
    case Completely = 'completely';

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
            self::NotAtAll => 'Not at all',
            self::Somewhat => 'Some of it',
            self::Mostly => 'Mostly',
            self::Completely => 'Yes, exactly',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::NotAtAll => 0,
            self::Somewhat => 1,
            self::Mostly => 2,
            self::Completely => 3,
        };
    }

    /**
     * An answer worth reading a transcript over. Disagreement is the signal
     * the blueprint asks us to watch, and it is the one a satisfied average
     * hides most effectively.
     */
    public function isDissent(): bool
    {
        return $this->rank() <= 1;
    }
}
