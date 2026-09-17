<?php

namespace App\Enums;

/**
 * An action's lifecycle. As with gaps, there is no deleted state: an action
 * that did not happen is skipped, and skipping is information.
 */
enum ActionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Skipped = 'skipped';

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
            self::Active => 'In progress',
            self::Completed => 'Done',
            self::Skipped => 'Set aside',
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Active;
    }
}
