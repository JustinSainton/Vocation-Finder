<?php

namespace App\Enums;

/**
 * What kind of place this is.
 *
 * The explorer carries community colleges, technical schools and trade
 * programmes in the same list as universities on purpose. A student whose
 * pathway runs through a two-year welding certificate should not have to leave
 * the "college explorer" to find it, because leaving is how a student learns
 * that their path is the lesser one.
 */
enum CollegeKind: string
{
    case University = 'university';
    case LiberalArts = 'liberal_arts';
    case Community = 'community';
    case Technical = 'technical';

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
            self::University => 'University',
            self::LiberalArts => 'Liberal arts college',
            self::Community => 'Community college',
            self::Technical => 'Technical or trade school',
        };
    }
}
