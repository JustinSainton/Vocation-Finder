<?php

namespace App\Enums;

/**
 * A gap's lifecycle. There is no deleted state, by design: the brain freezes
 * and closes, it never destroys. See the brain persistence policy.
 */
enum GapStatus: string
{
    case Open = 'open';
    case Testing = 'testing';
    case Closed = 'closed';

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
            self::Open => 'Open',
            self::Testing => 'Being tested',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }
}
