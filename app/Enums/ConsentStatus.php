<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Revoked = 'revoked';

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
            self::Pending => 'Waiting on a parent',
            self::Granted => 'Granted',
            self::Revoked => 'Withdrawn',
        };
    }
}
