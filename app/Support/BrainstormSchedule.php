<?php

namespace App\Support;

use App\Enums\ActionStatus;
use App\Models\BrainstormSchedule as Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Roadmap 2.4 — scheduled brainstorm sessions, monthly by default, adaptive.
 *
 * ## Why a cadence at all
 *
 * The vision's return loop is voluntary: the product's claim is that a student
 * comes back because there is a reason to, not because it buzzed. A cadence
 * is how the system decides when it has earned the right to ask — it is a
 * ceiling on contact, not a schedule of it.
 *
 * ## Adaptive on evidence, never on marketing
 *
 * A student who settled their last action is moving and gets asked back
 * sooner, because they want it. A student who has let invitations pass gets
 * asked back later, and then later again, because the alternative is a
 * notification a sixteen-year-old learns to ignore — and once they have
 * learned that, the one invitation that mattered is ignored too.
 *
 * It never stops entirely. `MAX_CADENCE_DAYS` is a floor on contact, not a
 * countdown to abandonment: the brain is never deleted and neither is the
 * standing offer to come back to it.
 *
 * ## The invitation is never an empty prompt
 *
 * An invitation with nothing in it is a nag. When {@see ThresholdSurfacing}
 * has something the student has said repeatedly, that is what the session
 * opens with — their own words, not a generated topic.
 */
class BrainstormSchedule
{
    public const DEFAULT_CADENCE_DAYS = 30;

    public const ENGAGED_CADENCE_DAYS = 21;

    public const MAX_CADENCE_DAYS = 90;

    public function for(User $user): Schedule
    {
        return Schedule::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Whether it is time to invite this student back.
     *
     * A student who has never been invited is not due immediately: the first
     * session is the assessment, and inviting someone to brainstorm on the
     * day they finished is the product talking rather than listening.
     */
    public function isDue(User $user, ?CarbonImmutable $asOf = null): bool
    {
        $now = $asOf ?? CarbonImmutable::now();
        $schedule = $this->for($user);

        $since = $schedule->last_invited_at ?? $schedule->last_attended_at ?? $user->created_at;

        if ($since === null) {
            return false;
        }

        return CarbonImmutable::parse($since)->addDays($schedule->cadence_days)->lessThanOrEqualTo($now);
    }

    /**
     * The invitation, or null if it is not time or there is nothing to say.
     *
     * @return array{opens_with: array<string, mixed>|null, prompt: string}|null
     */
    public function invitation(User $user, ?CarbonImmutable $asOf = null): ?array
    {
        if (! $this->isDue($user, $asOf)) {
            return null;
        }

        $pattern = (new ThresholdSurfacing)->detect($user, $asOf);

        /**
         * A pattern built from distress is not a brainstorm topic. It is
         * routed to a person, and the invitation goes out without it rather
         * than interpreting it — crisis reaches a human before it reaches
         * vocational meaning.
         */
        if ($pattern && $pattern['needs_human']) {
            $pattern = null;
        }

        return [
            'opens_with' => $pattern,
            'prompt' => $pattern
                ? "You have come back to this {$pattern['entry_count']} times since {$pattern['first_said_on']}. Here is what you said. Is it still true?"
                : 'It has been a while. What has changed since we last talked?',
        ];
    }

    /**
     * The student showed up. Record it, and move the cadence on evidence.
     */
    public function attended(User $user, ?CarbonImmutable $asOf = null): Schedule
    {
        $now = $asOf ?? CarbonImmutable::now();
        $schedule = $this->for($user);

        $moving = $user->actions()
            ->where('status', ActionStatus::Completed->value)
            ->where('settled_at', '>=', $now->subDays($schedule->cadence_days * 2))
            ->exists();

        $schedule->forceFill([
            'last_attended_at' => $now,
            'last_invited_at' => $now,
            'consecutive_declines' => 0,
            'cadence_days' => $moving ? self::ENGAGED_CADENCE_DAYS : self::DEFAULT_CADENCE_DAYS,
        ])->save();

        return $schedule;
    }

    /**
     * The invitation went out and nothing came of it. Back off.
     *
     * Doubling rather than incrementing, because the information in a second
     * ignored invitation is not "ask again in a month and a day" — it is that
     * the current rhythm is wrong.
     */
    public function declined(User $user, ?CarbonImmutable $asOf = null): Schedule
    {
        $now = $asOf ?? CarbonImmutable::now();
        $schedule = $this->for($user);

        $schedule->forceFill([
            'last_invited_at' => $now,
            'consecutive_declines' => $schedule->consecutive_declines + 1,
            'cadence_days' => min(self::MAX_CADENCE_DAYS, $schedule->cadence_days * 2),
        ])->save();

        return $schedule;
    }
}
