<?php

namespace App\Enums;

/**
 * Blueprint 10.3 requires aspirational and demonstrated fit to be evaluated
 * separately, because "the distance between the two generates the development
 * plan and real-world experiments" — not automatic validation, and not
 * dismissal.
 *
 * A signal that is only ever aspirational is not a deficiency. It is the raw
 * material of the next experiment.
 */
enum SignalTrack: string
{
    /** What they want, admire, or imagine. */
    case Aspiration = 'aspiration';

    /** What they have practised, endured, produced, or been trusted to carry. */
    case Demonstrated = 'demonstrated';

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
            self::Aspiration => 'Aspiration',
            self::Demonstrated => 'Demonstrated',
        };
    }
}
