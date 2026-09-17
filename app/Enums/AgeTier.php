<?php

namespace App\Enums;

/**
 * Entitlement by age band. Distinct from life stage, which drives relevance.
 *
 * The vision is explicit that these are two different concepts and must stay
 * two different concepts in code: a syllabus uploader is useless to a high
 * schooler and the college success layer only matters once enrolled, but
 * neither is a paywall — "that's not tiering, that's just relevance."
 */
enum AgeTier: string
{
    /** Assessment and vocational portrait only. No coach, no brain. */
    case FreshmanSophomore = 'freshman_sophomore';

    /** The full coach and the full brain — not a limited version. */
    case JuniorSenior = 'junior_senior';

    /** Everything, self-pay, no parent involvement of any kind. */
    case Adult = 'adult';

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
            self::FreshmanSophomore => 'Freshman or sophomore',
            self::JuniorSenior => 'Junior or senior',
            self::Adult => 'Post high school',
        };
    }

    /**
     * Whether this tier gets the coach and the brain at all.
     *
     * Juniors and seniors get the full thing deliberately. Junior and senior
     * year is the decision moment, which is exactly where decision friction
     * does the most damage, and a coach that cannot help with the thing you
     * are actually facing teaches a student it is not for them.
     */
    public function hasCoach(): bool
    {
        return $this !== self::FreshmanSophomore;
    }

    public function hasBrain(): bool
    {
        return $this->hasCoach();
    }

    /**
     * Minors need a parent to consent and a parent to pay. Adults must have
     * neither — 18+ is self-pay with no parent involvement and no parent
     * reporting at all.
     */
    public function isMinor(): bool
    {
        return $this !== self::Adult;
    }

    /**
     * Freshmen and sophomores get the portrait free; it is the lead
     * generation tier and there is nothing to bill a parent for.
     */
    public function requiresParentCheckout(): bool
    {
        return $this === self::JuniorSenior;
    }

    public function requiresParentConsent(): bool
    {
        return $this === self::JuniorSenior;
    }

    /**
     * Whether a parent may receive progress reporting for this student.
     */
    public function permitsParentReporting(): bool
    {
        return $this->isMinor();
    }
}
