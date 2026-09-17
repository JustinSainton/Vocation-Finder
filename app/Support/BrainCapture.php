<?php

namespace App\Support;

use App\Enums\BrainEntrySource;
use App\Models\Action;
use App\Models\Assessment;
use App\Models\BrainEntry;
use App\Models\User;
use RuntimeException;

/**
 * Writes a student's own words into the vocational brain.
 *
 * Capture is the part of this product that cannot be backfilled. A
 * conversation that happens before capture exists is gone permanently, which
 * is why this layer is deliberately biased toward keeping too much rather than
 * too little.
 *
 * The one thing it will never do is store words the student did not say. Every
 * public method takes the student's text as its subject; the coach's own
 * sentences reach this class only as `context`, never as `content`. That is
 * the whole line: the brain surfaces what a student already said, it does not
 * write what they would have said.
 */
class BrainCapture
{
    /**
     * Below this, a turn is acknowledgement rather than material ("ok", "yeah
     * I guess", "idk").
     *
     * Deliberately crude and deliberately low. A cleverer filter would need a
     * model to judge whether something was worth keeping, and a model that
     * decides what is worth remembering about a teenager is a worse failure
     * than a brain with some "I don't know" in it. Length is computable, and
     * per the guardrail principle we never ask a model for a value we can
     * compute.
     *
     * Note this is a floor on *material*, not on polish — it does not reward
     * vocabulary, spelling or construction. See ResponseQuality for why that
     * distinction is load-bearing.
     */
    public const MIN_WORDS = 6;

    /**
     * A turn the student typed or spoke to the coach.
     *
     * Returns null rather than throwing for the coach's own turns, so a call
     * site can hand every turn in a conversation to this method without having
     * to know the rule. The filtering is the point of the method.
     */
    public function captureCoachTurn(
        User $user,
        string $role,
        string $content,
        ?string $prompt = null,
        ?string $audioStoragePath = null,
    ): ?BrainEntry {
        if (strtolower($role) !== 'user') {
            return null;
        }

        if (! static::isSubstantive($content)) {
            return null;
        }

        return $this->capture($user, BrainEntrySource::Coach, $content, $prompt, audioStoragePath: $audioStoragePath);
    }

    /**
     * What a student wrote about how a finished action actually went.
     *
     * The reflection is often the most valuable thing in the brain: it is the
     * student describing real experience rather than an intention. Idempotent
     * per action, so re-settling or a replayed job cannot duplicate it.
     */
    public function captureActionReflection(Action $action): ?BrainEntry
    {
        $reflection = trim((string) $action->reflection);

        if ($reflection === '' || ! static::isSubstantive($reflection)) {
            return null;
        }

        if (BrainEntry::where('action_id', $action->id)->exists()) {
            return null;
        }

        return $this->capture(
            $action->user,
            BrainEntrySource::Action,
            $reflection,
            "After finishing: {$action->title}",
            action: $action,
        );
    }

    /**
     * The student choosing to keep something: "save this on my brain."
     *
     * No substantive floor here. If a student deliberately saves four words,
     * those four words matter to them, and we are not the judge of that.
     */
    public function captureDirect(User $user, string $content, ?string $audioStoragePath = null): BrainEntry
    {
        return $this->capture($user, BrainEntrySource::Direct, $content, null, audioStoragePath: $audioStoragePath);
    }

    /**
     * Assessment answers, so the brain starts with something in it.
     *
     * Runs once per assessment. A student who finishes the assessment and
     * opens the brain to an empty page has been told the product remembers
     * them and shown that it does not.
     *
     * @return list<BrainEntry>
     */
    public function captureAssessment(Assessment $assessment): array
    {
        if (! $assessment->user || BrainEntry::where('assessment_id', $assessment->id)->exists()) {
            return [];
        }

        $entries = [];

        foreach ($assessment->answers()->with('question')->get() as $answer) {
            $text = trim((string) $answer->response_text);

            if ($text === '' || ! static::isSubstantive($text)) {
                continue;
            }

            $entries[] = $this->capture(
                $assessment->user,
                BrainEntrySource::Assessment,
                $text,
                $answer->question?->question_text,
                assessment: $assessment,
                occurredAt: $answer->created_at,
            );
        }

        return $entries;
    }

    /**
     * Whether a fragment carries enough to be worth keeping forever.
     */
    public static function isSubstantive(string $content): bool
    {
        return WordCount::of($content) >= self::MIN_WORDS;
    }

    protected function capture(
        User $user,
        BrainEntrySource $source,
        string $content,
        ?string $context = null,
        ?Action $action = null,
        ?Assessment $assessment = null,
        ?string $audioStoragePath = null,
        mixed $occurredAt = null,
    ): BrainEntry {
        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('A brain entry has to contain something the student said.');
        }

        /**
         * A lapsed or absent entitlement freezes the brain: nothing new goes
         * in. It never destroys what is there, and it never blocks reading or
         * export — see BrainRetrieval, which checks nothing at all.
         */
        if (! AccessPolicy::canUseBrain($user)) {
            throw new RuntimeException(
                AccessPolicy::coachBlockedReason($user) ?? 'This student does not have a brain yet.'
            );
        }

        return BrainEntry::create([
            'user_id' => $user->id,
            'action_id' => $action?->id,
            'assessment_id' => $assessment?->id,
            'source' => $source,
            'content' => $content,
            'context' => filled($context) ? trim($context) : null,
            'audio_storage_path' => $audioStoragePath,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
