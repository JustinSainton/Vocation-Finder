<?php

namespace App\Enums;

/**
 * A reviewer's answer, in words for the same reason every other standing here
 * is: a five-point scale across nine dimensions produces a composite score,
 * and a composite score is a thing people start optimising instead of reading.
 */
enum ReviewStanding: string
{
    case Fails = 'fails';
    case Partly = 'partly';
    case Holds = 'holds';

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
            self::Fails => 'No',
            self::Partly => 'Partly',
            self::Holds => 'Yes',
        };
    }

    /**
     * A portrait that fails any one dimension is a portrait to look at, and
     * eight passes do not cancel one failure. This is the reason there is no
     * total.
     */
    public function isFailure(): bool
    {
        return $this === self::Fails;
    }
}
