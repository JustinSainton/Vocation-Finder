<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\User;

/**
 * Whether the coach has a portrait to ground itself in.
 *
 * The results API returns a profile as soon as {@see AnalyzeAssessmentJob}
 * persists it, which can be a moment before the assessment row flips to
 * `completed`. Tools and openers that only looked at `status = completed`
 * therefore saw no profile while the student was already reading their
 * portrait — and the first opener was recorded without it, never to be
 * replaced.
 */
class PathwayProfileReadiness
{
    public const STATUS_READY = 'ready';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_NONE = 'none';

    public function latestPortraitAssessment(User $user): ?Assessment
    {
        return $user->assessments()
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();
    }

    public function hasPortrait(User $user): bool
    {
        return $this->latestPortraitAssessment($user) !== null;
    }

    public function portraitStatus(User $user): string
    {
        if ($this->hasPortrait($user)) {
            return self::STATUS_READY;
        }

        if ($user->assessments()->where('status', 'analyzing')->exists()) {
            return self::STATUS_ANALYZING;
        }

        return self::STATUS_NONE;
    }

    public function assessmentId(User $user): ?string
    {
        return $this->latestPortraitAssessment($user)?->id
            ?? $user->assessments()->where('status', 'analyzing')->latest()->value('id');
    }

    public function awaitingMessage(): string
    {
        return 'Your portrait is still being prepared. Your coach will open the conversation from it as soon as it is ready.';
    }
}
