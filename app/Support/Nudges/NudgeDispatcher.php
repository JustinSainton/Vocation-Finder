<?php

namespace App\Support\Nudges;

use App\Models\User;
use App\Support\AccessPolicy;
use App\Support\BrainstormSchedule;
use Illuminate\Support\Facades\App;

/**
 * Everything that has to be true before a student's phone is allowed to make
 * a noise.
 *
 * This exists now, with no real channel behind it, because the decision that
 * was deferred is *which carrier*, not *what the rules are*. The rules are
 * product decisions and they are already settled — writing them down here
 * means a future push or Live Activity driver is forty lines of provider
 * glue rather than a fresh argument about whether to text a fifteen-year-old
 * at eleven at night.
 *
 * The order is deliberate: entitlement, then timing, then content. A student
 * who is not entitled is never asked about, so no schedule row is touched on
 * behalf of somebody we may not contact.
 */
class NudgeDispatcher
{
    public function __construct(
        protected BrainstormSchedule $schedule = new BrainstormSchedule,
    ) {}

    /**
     * Send the invitation if there is one to send. Returns what was sent, so
     * a caller can log it, or null when nothing left the building.
     */
    public function nudge(User $user): ?Nudge
    {
        if (! AccessPolicy::canUseCoach($user)) {
            return null;
        }

        $invitation = $this->schedule->invitation($user);

        /*
         * Null means either "not due" or "nothing to say", and both are
         * reasons to stay quiet. An invitation with nothing in it is a nag,
         * and the dispatcher is not permitted to manufacture a reason the
         * schedule declined to give it.
         */
        if (! $invitation) {
            return null;
        }

        $channel = $this->channel();

        if (! $channel->canReach($user)) {
            return null;
        }

        $nudge = new Nudge(
            body: $invitation['prompt'],
            url: url('/coach'),
            reason: $invitation['opens_with'] ? 'pattern' : 'cadence',
        );

        $channel->deliver($user, $nudge);

        /*
         * Recording the send is what stops it going out again tomorrow, and
         * `declined()` is the right recorder even though nothing has been
         * declined yet: it stamps the invitation and backs the rhythm off on
         * the assumption that silence follows. The assumption is provisional
         * and self-healing — `attended()` zeroes the decline count and resets
         * the cadence the moment the student turns up, which is the existing
         * behaviour of the coach page.
         *
         * Until now nothing in the application called `declined()` at all.
         * The pushed nudge is the caller 2.4 was written for, which is why
         * "double the interval when an invitation is ignored" has never
         * actually had a chance to happen.
         */
        $this->schedule->declined($user);

        return $nudge;
    }

    /**
     * The configured channel, falling back to silence rather than to
     * whichever driver happens to be registered first.
     */
    public function channel(): NudgeChannel
    {
        $configured = config('vocation.nudges.channel');
        $channels = config('vocation.nudges.channels', []);

        $class = $channels[$configured] ?? null;

        if (! $class || ! is_subclass_of($class, NudgeChannel::class)) {
            return new SilentChannel;
        }

        return App::make($class);
    }
}
