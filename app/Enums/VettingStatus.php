<?php

namespace App\Enums;

/**
 * Whether a listing has been through the vetting pass.
 *
 * `Pending` is the default and it means **invisible**, not "shown with a
 * warning". The vision asks for "a vetted process that filters out spam job
 * posts and eliminates them from the site", and the students on the other side
 * of this are sixteen. A warned-about listing is a listing somebody clicks;
 * the only safe default for an unexamined post is absence.
 */
enum VettingStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Rejected = 'rejected';

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
            self::Pending => 'Not yet checked',
            self::Passed => 'Checked',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Only one case may ever reach a student, and it is not the default.
     */
    public function isShowable(): bool
    {
        return $this === self::Passed;
    }
}
