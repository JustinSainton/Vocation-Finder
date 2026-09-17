<?php

namespace App\Support\Nudges;

use App\Models\User;
use App\Support\BrainstormSchedule;

/**
 * A way of getting an invitation in front of a student.
 *
 * Deliberately narrow. A channel decides **whether it can reach this person**
 * and **how to carry a sentence**; it never decides *when* to speak or *what*
 * to say. Cadence lives in {@see BrainstormSchedule} and the
 * words come from the student's own material — a channel that scheduled
 * itself would fork the cadence, and two things deciding when to contact a
 * teenager is how a product starts nagging.
 *
 * Nothing implements this against a real provider yet, and that is the point
 * of it existing: the likely first driver is a push notification or an iOS
 * Live Activity through the Expo app, not SMS. Whichever it is, it drops in
 * here without touching the schedule, the wording or the consent gate.
 */
interface NudgeChannel
{
    /**
     * Whether this student can be reached this way at all — a registered
     * device, a verified number, a granted permission.
     */
    public function canReach(User $user): bool;

    /**
     * Carry the invitation. Implementations must not re-check timing; by the
     * time they are called, {@see NudgeDispatcher} has already established
     * that this student is due, entitled and has something worth being sent.
     */
    public function deliver(User $user, Nudge $nudge): void;
}
