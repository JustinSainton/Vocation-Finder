<?php

namespace App\Enums;

/**
 * A habit's lifecycle. As with gaps and actions there is no deleted state: a
 * habit that stopped is retired, and having tried it is information about the
 * student that outlives the trying.
 */
enum HabitStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Retired = 'retired';

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
            self::Active => 'Going',
            self::Paused => 'On hold',
            self::Retired => 'Finished with',
        };
    }

    public function isRunning(): bool
    {
        return $this === self::Active;
    }
}
