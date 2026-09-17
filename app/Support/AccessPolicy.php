<?php

namespace App\Support;

use App\Enums\AgeTier;
use App\Models\User;

/**
 * Who may use what, and what a parent is allowed to see.
 *
 * Two rules here are product invariants rather than configuration, and both
 * are enforced as code rather than as UI:
 *
 * 1. A freshman or sophomore does not get the coach or the brain **at any
 *    price**. This is a policy gate, not a paywall, so no subscription state
 *    can open it.
 * 2. A parent never sees coaching conversations. "A student who knows their
 *    parent is reading everything will not be honest with the coach, and the
 *    honesty is what the whole thing runs on."
 */
class AccessPolicy
{
    /**
     * The student's entitlement band.
     *
     * Grade decides the school-age bands because a sixteen-year-old may be a
     * sophomore or a junior. Age decides adulthood because that boundary is
     * legal. When grade is unknown, age is the fallback rather than a refusal:
     * a student who did not fill in a form should not silently lose the coach.
     */
    public static function tier(User $user): AgeTier
    {
        if (static::isAdult($user)) {
            return AgeTier::Adult;
        }

        if ($user->grade_level !== null) {
            return $user->grade_level >= 11
                ? AgeTier::JuniorSenior
                : AgeTier::FreshmanSophomore;
        }

        return ($user->birthdate?->age ?? 0) >= 16
            ? AgeTier::JuniorSenior
            : AgeTier::FreshmanSophomore;
    }

    /**
     * Unknown birthdates are treated as minors. Guessing the other way would
     * skip consent for someone who needed it, and the costs are not symmetric.
     */
    public static function isAdult(User $user): bool
    {
        return $user->birthdate !== null && $user->birthdate->age >= 18;
    }

    public static function requiresParentConsent(User $user): bool
    {
        return static::tier($user)->requiresParentConsent();
    }

    public static function requiresParentCheckout(User $user): bool
    {
        return static::tier($user)->requiresParentCheckout();
    }

    public static function hasParentConsent(User $user): bool
    {
        return $user->parentConsents()->granted()->exists();
    }

    /**
     * Whether this student may use the coach right now.
     *
     * Ordered deliberately: the tier gate is checked before consent, so a
     * freshman is refused for being a freshman and not for a missing consent
     * form that would not have helped them anyway.
     */
    public static function canUseCoach(User $user): bool
    {
        $tier = static::tier($user);

        if (! $tier->hasCoach()) {
            return false;
        }

        return ! $tier->requiresParentConsent() || static::hasParentConsent($user);
    }

    public static function canUseBrain(User $user): bool
    {
        return static::canUseCoach($user);
    }

    /**
     * Why the coach is unavailable, for showing the student.
     */
    public static function coachBlockedReason(User $user): ?string
    {
        $tier = static::tier($user);

        if (! $tier->hasCoach()) {
            return 'The coach opens in junior year. For now the assessment and your portrait are yours to keep.';
        }

        if ($tier->requiresParentConsent() && ! static::hasParentConsent($user)) {
            return 'We need a parent or guardian to say yes before the coach can start.';
        }

        return null;
    }

    /**
     * Whether the brain is frozen: nothing new goes in, everything already in
     * it stays readable and exportable.
     *
     * This is the whole of the freeze-not-delete policy in one predicate.
     * "Frozen" is a statement about *capture* only — {@see BrainRetrieval}
     * deliberately consults nothing, because a student who stops paying still
     * owns every word they wrote. Anything that gates reading or export on
     * this method is a bug.
     */
    public static function brainIsFrozen(User $user): bool
    {
        return ! static::canUseBrain($user);
    }

    /**
     * Always true, and stated as a method so the invariant has somewhere to
     * live and something to test.
     *
     * A student's own words are theirs whether or not anyone is paying, and
     * whether or not a parent has changed their mind. We promise this to
     * parents in writing at the moment they consent.
     */
    public static function canExportBrain(User $user): bool
    {
        return true;
    }

    /**
     * An 18+ student has no parent involvement and no parent reporting at all.
     */
    public static function permitsParentReporting(User $user): bool
    {
        return static::tier($user)->permitsParentReporting();
    }
}
