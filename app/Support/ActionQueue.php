<?php

namespace App\Support;

use App\Models\Action;
use App\Models\Gap;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Assigns and settles the single next step a student is working on.
 *
 * The vision's success test is whether a student leaves able to describe one
 * thing they are going to do. Two constraints follow, and both are enforced
 * here rather than asked for politely:
 *
 * 1. **One at a time.** A second action cannot be assigned while one is live.
 * 2. **One thing.** An action whose text is really a list of three is a list
 *    wearing a queue's clothing, and it reintroduces exactly the friction the
 *    queue removes.
 */
class ActionQueue
{
    /**
     * Longer than this and it is a plan, not a step.
     */
    public const MAX_TITLE_LENGTH = 200;

    /**
     * Unambiguous list markers. Deliberately narrow: this must not reject
     * "Ask your aunt's friend who is a nurse what her hardest week looks
     * like", which is one action containing several clauses.
     */
    protected const LIST_MARKERS = [
        '/[\r\n]/u',
        '/;/u',
        '/(?:^|\s)[-*•]\s/u',
        '/(?:^|\s)\d+[.)]\s/u',
        '/\b(?:first|firstly)\b.*\b(?:then|second|secondly|after that)\b/iu',
    ];

    public function current(User $user): ?Action
    {
        return $user->actions()->active()->first();
    }

    /**
     * Assign the next step.
     *
     * Refuses rather than replaces: a coach that can silently swap a student's
     * action out from under them produces a moving target, which is the same
     * paralysis as a list, delivered one item at a time.
     */
    public function assign(User $user, string $title, ?string $rationale = null, ?Gap $gap = null): Action
    {
        $title = trim($title);

        if ($existing = $this->current($user)) {
            throw new RuntimeException(
                "This student already has an action in progress: \"{$existing->title}\". Settle that one before assigning another."
            );
        }

        if ($title === '') {
            throw new RuntimeException('An action needs to say what to do.');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new RuntimeException('That is a plan, not a step. Give them one thing, in one sentence.');
        }

        if (! self::isSingleAction($title)) {
            throw new RuntimeException('That is a list. Give them one thing — the list is the friction we are removing.');
        }

        if ($gap && ! $gap->user->is($user)) {
            throw new RuntimeException('That gap belongs to a different student.');
        }

        return Action::create([
            'user_id' => $user->id,
            'gap_id' => $gap?->id,
            'title' => $title,
            'rationale' => $rationale,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Whether a title describes one step rather than several.
     */
    public static function isSingleAction(string $title): bool
    {
        foreach (self::LIST_MARKERS as $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Complete the current action, closing the gap it aimed at.
     *
     * Closing the gap here is the point of the whole chain: gaps are what
     * actions are for, and a completed action that left its gap open would
     * mean the student did the thing and the system did not notice.
     *
     * The reflection is captured to the brain here rather than at a call site
     * because this is the moment it exists, and a reflection that is not kept
     * now is not recoverable later. It is also the highest-value thing in the
     * brain: a student describing what actually happened rather than what they
     * hope for.
     */
    public function complete(Action $action, ?string $reflection = null): Action
    {
        $action->complete($reflection);

        $action->gap?->close();

        try {
            (new BrainCapture)->captureActionReflection($action->fresh());
        } catch (RuntimeException $exception) {
            // Settling an action must never fail because the brain refused
            // the write — consent can be revoked between assignment and
            // completion, and the student still finished the thing.
            Log::warning('brain_capture_failed', [
                'action_id' => $action->id,
                'reason' => $exception->getMessage(),
            ]);
        }

        (new ReadinessCalculator)->record($action->user, 'You finished something.');

        return $action->fresh();
    }

    /**
     * Set the action aside, leaving its gap open.
     *
     * A skipped step is information about the step — usually that it was the
     * wrong size or aimed at the wrong gap — and never a verdict on the
     * student.
     */
    public function skip(Action $action, ?string $reason = null): Action
    {
        $action->skip($reason);

        /**
         * Recorded, but with no claim that skipping lowered anything. The
         * standings are recomputed from what exists; a skipped step simply
         * did not add to the tested-it factor. Docking a student for setting
         * something aside would make the metric a punishment, and they would
         * stop telling us when a step was wrong.
         */
        (new ReadinessCalculator)->record($action->user, 'You set a step aside.');

        return $action->fresh();
    }
}
