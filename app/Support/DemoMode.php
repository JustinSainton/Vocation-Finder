<?php

namespace App\Support;

use App\Enums\ClarityStanding;
use App\Models\Question;
use App\Models\User;

/**
 * Whether this person sees the assessment pre-filled.
 *
 * Two locks, and both must be open: the environment has to switch demo mode on
 * (`VOCATION_DEMO_MODE`), and the signed-in account has to hold the `demo`
 * role. A global feature flag was the obvious alternative and the wrong one —
 * it would pre-fill every student's assessment the moment someone toggled it,
 * and a student handed a finished answer is no longer being asked anything.
 * Guests are never in demo mode, because a guest is by definition someone we
 * know nothing about.
 *
 * Nothing about the answers is special once they are saved: the student-facing
 * text is still editable, the save still runs {@see CrisisCheck}, and the
 * analysis reads them the way it reads anybody's.
 */
class DemoMode
{
    public const ROLE = 'demo';

    public static function isActiveFor(?User $user): bool
    {
        return (bool) config('vocation.demo.enabled')
            && $user !== null
            && $user->role === self::ROLE;
    }

    /**
     * What a client needs before the questions load: the persona's name for
     * the indicator and the clarity readings, which are asked before and after
     * the questions rather than as one of them.
     *
     * @return array{persona: string, clarity: array{before: string, after: string}}|null
     */
    public static function payloadFor(?User $user): ?array
    {
        if (! self::isActiveFor($user)) {
            return null;
        }

        return [
            'persona' => DemoPersona::NAME,
            'clarity' => [
                'before' => ClarityStanding::VagueSense->value,
                'after' => ClarityStanding::FewOptions->value,
            ],
        ];
    }

    public static function answerFor(?User $user, Question $question): ?string
    {
        if (! self::isActiveFor($user)) {
            return null;
        }

        return DemoPersona::answerFor($question);
    }
}
