<?php

namespace App\Enums;

/**
 * A person's standing inside an organization.
 *
 * The column has been a bare string with a `member` default since the schema
 * was written, and the three values were spelled out at a dozen call sites.
 * A closed vocabulary gets an enum here for the same reason every closed
 * vocabulary in the engine does: so that "which roles are staff?" has one
 * answer rather than a `whereIn` written from memory each time.
 */
enum OrganizationRole: string
{
    case Admin = 'admin';
    case Mentor = 'mentor';
    case Member = 'member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The roles that act on behalf of the organization rather than being
     * served by it. A counsellor is a mentor; a principal is an admin. The
     * student is never either.
     *
     * @return list<string>
     */
    public static function staff(): array
    {
        return [self::Admin->value, self::Mentor->value];
    }

    public function isStaff(): bool
    {
        return in_array($this->value, self::staff(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Mentor => 'Counsellor',
            self::Member => 'Student',
        };
    }
}
