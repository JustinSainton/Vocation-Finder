<?php

namespace App\Enums;

/**
 * What sort of thing is in the locker.
 *
 * Closed, like every vocabulary here, so "essay" and "Essay" and "english
 * paper" cannot become three kinds of thing that nothing can group.
 */
enum ArtifactKind: string
{
    case Resume = 'resume';
    case Essay = 'essay';
    case Portfolio = 'portfolio';
    case Certificate = 'certificate';
    case Other = 'other';

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
            self::Resume => 'Résumé',
            self::Essay => 'Essay',
            self::Portfolio => 'Something you made',
            self::Certificate => 'Certificate',
            self::Other => 'Other',
        };
    }
}
