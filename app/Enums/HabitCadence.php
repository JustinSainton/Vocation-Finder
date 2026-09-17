<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How often a habit is meant to come round.
 *
 * Deliberately three, not a number the student picks. "Every 3 days" is a
 * schedule; a habit is something that attaches to the shape of a week, and a
 * teenager who cannot say when it happens has not been given a habit.
 */
enum HabitCadence: string
{
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';

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
            self::Daily => 'Every day',
            self::Weekdays => 'School days',
            self::Weekly => 'Once a week',
        };
    }

    /**
     * How many occasions this cadence expects across a span of days. This is
     * the denominator the standing is judged against, and it is arithmetic
     * rather than a judgement — the same posture readiness takes.
     */
    public function occasionsIn(CarbonInterface $from, CarbonInterface $to): int
    {
        $days = max(0, $from->startOfDay()->diffInDays($to->startOfDay())) + 1;

        return match ($this) {
            self::Daily => $days,
            self::Weekdays => $this->weekdaysBetween($from, $to),
            self::Weekly => (int) ceil($days / 7),
        };
    }

    /**
     * The span the standing looks back over. Long enough that one bad week
     * does not define a habit, short enough that it still describes now.
     */
    public function windowInDays(): int
    {
        return match ($this) {
            self::Daily, self::Weekdays => 14,
            self::Weekly => 42,
        };
    }

    protected function weekdaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $count = 0;
        $cursor = CarbonImmutable::parse($from)->startOfDay();
        $last = CarbonImmutable::parse($to)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
