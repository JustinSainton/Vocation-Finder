<?php

namespace App\Support;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A school's invitation, and what it is actually worth at the moment it is
 * used.
 *
 * An invitation is a copy of a permission, and copies go stale. It was written
 * when the roster had room, when the token had not expired and when nobody had
 * used it yet — none of which is still guaranteed by the time a fifteen-year-old
 * gets round to opening the email. So every one of those is checked *here*, at
 * accept time, rather than trusted from when the row was created.
 *
 * The refusals are deliberately separate messages. "Something went wrong" sends
 * a student to their counsellor with nothing to say; "this school has no seats
 * left" sends them with the actual sentence that fixes it.
 */
class CohortInvitation
{
    /**
     * The invitation behind a token, if it is still good for anything.
     */
    public function find(string $token): ?OrganizationInvitation
    {
        return OrganizationInvitation::query()
            ->where('token', $token)
            ->with('organization')
            ->first();
    }

    /**
     * Why this invitation cannot be used, or null if it can.
     */
    public function blockedReason(OrganizationInvitation $invitation): ?string
    {
        if ($invitation->accepted_at) {
            return 'This invitation has already been used.';
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return 'This invitation has expired. Ask whoever sent it for a new link.';
        }

        if (! $this->hasRoom($invitation)) {
            return "{$invitation->organization->name} has no seats left. Ask them to free one up, and this link will work.";
        }

        return null;
    }

    /**
     * Join this account to the school, once.
     *
     * Returns false when the account was already a member — which is not an
     * error. A student who clicks the link twice has done nothing wrong, and
     * the invitation is spent either way.
     */
    public function accept(OrganizationInvitation $invitation, User $user): bool
    {
        if ($reason = $this->blockedReason($invitation)) {
            throw new RuntimeException($reason);
        }

        $organization = $invitation->organization;
        $joined = false;

        if (! $organization->users()->where('users.id', $user->id)->exists()) {
            $organization->users()->attach($user, [
                'id' => Str::uuid()->toString(),
                'role' => $invitation->role,
            ]);
            $joined = true;
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        return $joined;
    }

    /**
     * Seats are counted against accepted members and outstanding invitations
     * together. Counting only members lets a school hand out fifty links for
     * twenty seats and discover the problem one disappointed student at a
     * time.
     */
    protected function hasRoom(OrganizationInvitation $invitation): bool
    {
        $organization = $invitation->organization;

        $taken = $organization->users()->count()
            + $organization->invitations()
                ->whereNull('accepted_at')
                ->where('id', '!=', $invitation->id)
                ->count();

        return $taken < $organization->memberLimit();
    }
}
