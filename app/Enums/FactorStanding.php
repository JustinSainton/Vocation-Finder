<?php

namespace App\Enums;

/**
 * Where one contributing factor stands.
 *
 * Four words, no numbers, and the lowest is "not yet" rather than "none" or
 * "low". The difference matters: a student reading their own decomposition is
 * being handed a description of a week, not a grade.
 */
enum FactorStanding: string
{
    case NotYet = 'not_yet';
    case Beginning = 'beginning';
    case Building = 'building';
    case Solid = 'solid';

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
            self::NotYet => 'Not yet',
            self::Beginning => 'Beginning',
            self::Building => 'Building',
            self::Solid => 'Solid',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::NotYet => 0,
            self::Beginning => 1,
            self::Building => 2,
            self::Solid => 3,
        };
    }

    /**
     * Turn a count into a standing against three thresholds.
     *
     * @param  array{int, int, int}  $thresholds
     */
    public static function fromCount(int $count, array $thresholds): self
    {
        [$beginning, $building, $solid] = $thresholds;

        return match (true) {
            $count >= $solid => self::Solid,
            $count >= $building => self::Building,
            $count >= $beginning => self::Beginning,
            default => self::NotYet,
        };
    }
}
