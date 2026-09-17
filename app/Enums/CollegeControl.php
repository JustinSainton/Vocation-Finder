<?php

namespace App\Enums;

/**
 * Who runs the institution.
 *
 * Carried because it changes the cost question completely: a public school
 * charges two prices depending on where you live, and a private school charges
 * one. It is not a quality signal and is never rendered as one.
 */
enum CollegeControl: string
{
    case Public = 'public';
    case PrivateNonprofit = 'private_nonprofit';
    case PrivateForProfit = 'private_for_profit';

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
            self::Public => 'Public',
            self::PrivateNonprofit => 'Private, nonprofit',
            self::PrivateForProfit => 'Private, for-profit',
        };
    }

    /**
     * Whether the sticker price depends on where the student lives.
     */
    public function chargesResidentTuition(): bool
    {
        return $this === self::Public;
    }
}
