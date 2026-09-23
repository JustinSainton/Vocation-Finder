<?php

namespace App\Support;

use App\Data\Coach\CoachHandoffData;
use App\Enums\AgeTier;
use App\Models\Assessment;
use App\Models\User;
use App\Services\FeatureFlagService;

/**
 * Where the results page sends a student next.
 *
 * "Not a tool that hands the student a portrait and walks away." The portrait
 * ends in the coach, and this decides which door that is for the person
 * actually reading it: straight in, save the portrait first, ask a parent, or
 * — for a freshman — nothing to push through at all, because the portrait is
 * the whole product for them this year.
 *
 * The gate is {@see AccessPolicy::canUseCoach()}, the same predicate the coach
 * itself enforces, so the handoff can never promise a door that is locked.
 */
class CoachHandoff
{
    public static function for(?User $viewer, Assessment $assessment): ?CoachHandoffData
    {
        if (! app(FeatureFlagService::class)->isEnabled('pathway_coach')) {
            return null;
        }

        if ($assessment->status !== 'completed' || ! $assessment->vocationalProfile) {
            return null;
        }

        if (! $viewer) {
            return new CoachHandoffData(
                state: 'account',
                eyebrow: 'Your coach',
                headline: 'This portrait is where your coach starts.',
                body: 'Save it to your own account and your coach will open the conversation with what you wrote here — so you leave with one thing to do, not just a description.',
                href: '/register'.($assessment->guest_token ? '?guest_token='.urlencode($assessment->guest_token) : ''),
                cta: 'Save your portrait and meet your coach',
                starters: [],
            );
        }

        if ($assessment->user_id !== $viewer->id) {
            return null;
        }

        if (AccessPolicy::canUseCoach($viewer)) {
            return new CoachHandoffData(
                state: 'open',
                eyebrow: 'Your coach is ready',
                headline: 'Your coach has read this. It will speak first.',
                body: 'It starts from what you just wrote, asks what the questions could not, and ends with one concrete thing for you to do this week.',
                href: '/coach',
                cta: 'Start with your coach',
                starters: (new CoachStarters)->for($viewer),
            );
        }

        if (AccessPolicy::tier($viewer) === AgeTier::FreshmanSophomore) {
            return new CoachHandoffData(
                state: 'later',
                eyebrow: 'Your coach',
                headline: 'Keep this. Your coach opens in junior year.',
                body: AccessPolicy::coachBlockedReason($viewer) ?? '',
                href: null,
                cta: null,
                starters: [],
            );
        }

        if (AccessPolicy::requiresParentConsent($viewer) && ! AccessPolicy::hasParentConsent($viewer)) {
            return new CoachHandoffData(
                state: 'consent',
                eyebrow: 'Your coach',
                headline: 'One yes from a parent, and your coach starts here.',
                body: AccessPolicy::coachBlockedReason($viewer) ?? '',
                href: '/next',
                cta: 'Ask a parent or guardian',
                starters: [],
            );
        }

        /*
         * Consent before payment, always: a parent who has not said yes is
         * never shown a price for their child's coach.
         */
        return new CoachHandoffData(
            state: 'checkout',
            eyebrow: 'Your coach',
            headline: 'Your coach and your brain start from this portrait.',
            body: AccessPolicy::requiresParentCheckout($viewer)
                ? 'A parent or guardian starts your plan. Then your coach opens with what you wrote here and ends with one concrete thing to do this week.'
                : 'Start your plan and your coach opens with what you wrote here, and ends with one concrete thing to do this week.',
            href: '/billing',
            cta: 'Open the coach and the brain',
            starters: [],
        );
    }
}
