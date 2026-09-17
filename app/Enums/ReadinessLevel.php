<?php

namespace App\Enums;

/**
 * How ready a student is to actually move, in words.
 *
 * Deliberately never a number. DESIGN.md forbids percentages, dials and match
 * scores outright, and readiness is the most tempting place to break that rule
 * because a number is so much easier to render than a sentence. A student
 * shown "readiness: 42%" learns that they are 42% of a person.
 *
 * The lowest level is also deliberately not a verdict. "Not ready" would be a
 * statement about who someone is; {@see self::Considering} is a statement
 * about where they are this week, which is the only kind of claim this product
 * is entitled to make.
 */
enum ReadinessLevel: string
{
    case Considering = 'considering';
    case Exploring = 'exploring';
    case Testing = 'testing';
    case Moving = 'moving';

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
            self::Considering => 'Considering',
            self::Exploring => 'Exploring',
            self::Testing => 'Testing it',
            self::Moving => 'Moving',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Considering => 'You are thinking about it. That is a real place to be and everyone starts there.',
            self::Exploring => 'You have started naming what you want and what is in the way.',
            self::Testing => 'You are finding out by doing rather than by guessing.',
            self::Moving => 'You are doing the work and it is telling you things guessing never would.',
        };
    }

    /**
     * Ordering exists so history can say "this changed", not so anyone can be
     * ranked against anyone else.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Considering => 0,
            self::Exploring => 1,
            self::Testing => 2,
            self::Moving => 3,
        };
    }
}
