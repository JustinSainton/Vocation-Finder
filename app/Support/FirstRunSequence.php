<?php

namespace App\Support;

use App\Enums\FirstRunStep;
use App\Models\Assessment;
use App\Models\User;

/**
 * Where a student is in the first run, computed rather than stored.
 *
 * There is no `current_step` column on purpose. A stored pointer can disagree
 * with the data it describes — a student whose consent was revoked, whose
 * analysis failed, or who came back six weeks later — and every one of those
 * disagreements shows up as the product telling someone to do a thing they
 * have already done. Deriving it means the answer cannot be stale.
 *
 * The sequence is the vision's, in order: assessment, account, results, age
 * gate, checkout, refinement, first action. Readiness sits between refinement
 * and the first action in the vision and is **deliberately absent here** —
 * scored readiness is roadmap 1.3 and outside the MVP cut. It slots in without
 * reordering anything around it.
 */
class FirstRunSequence
{
    /**
     * The step a signed-in student is on right now.
     */
    public static function next(User $user): FirstRunStep
    {
        $assessment = static::latestAssessment($user);

        if (! $assessment) {
            return FirstRunStep::Assessment;
        }

        if ($assessment->status !== 'completed') {
            return FirstRunStep::Assessment;
        }

        if (! $assessment->vocationalProfile) {
            return FirstRunStep::Results;
        }

        /**
         * The age gate, in the order AccessPolicy already establishes: a
         * freshman is stopped for being a freshman, not for a missing consent
         * form that would not have helped them.
         */
        if (! AccessPolicy::tier($user)->hasCoach()) {
            return FirstRunStep::Portrait;
        }

        if (AccessPolicy::requiresParentConsent($user) && ! AccessPolicy::hasParentConsent($user)) {
            return FirstRunStep::ParentConsent;
        }

        if (! static::hasPaid($user)) {
            return FirstRunStep::Checkout;
        }

        if ($user->brainEntries()->where('source', 'coach')->doesntExist()) {
            return FirstRunStep::Refinement;
        }

        if ($user->actions()->doesntExist()) {
            return FirstRunStep::FirstAction;
        }

        return FirstRunStep::Complete;
    }

    /**
     * A guest's step, before there is a user to hang state on.
     */
    public static function nextForGuest(?Assessment $assessment): FirstRunStep
    {
        if (! $assessment || $assessment->status !== 'completed') {
            return FirstRunStep::Assessment;
        }

        return FirstRunStep::Account;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        $step = static::next($user);

        return [
            'step' => $step->value,
            'prompt' => $step->prompt(),
            'is_terminal' => $step->isTerminal(),
        ];
    }

    /**
     * Consent and payment are separate gates and stay separate.
     *
     * A parent who paid has not thereby consented, and a parent who consented
     * has not thereby paid. Collapsing them would let a payment stand in for
     * permission, which is the wrong direction for a minor's account.
     */
    protected static function hasPaid(User $user): bool
    {
        return $user->subscribed('default') || $user->onTrial();
    }

    protected static function latestAssessment(User $user): ?Assessment
    {
        return $user->assessments()->latest()->first();
    }
}
