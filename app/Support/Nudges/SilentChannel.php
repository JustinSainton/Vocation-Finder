<?php

namespace App\Support\Nudges;

use App\Models\User;

/**
 * The default, and the reason this seam is safe to leave half-built.
 *
 * It reaches nobody and sends nothing. A stub that quietly becomes live the
 * moment somebody sets an environment variable is the dangerous kind of stub,
 * so the fallback is not "try the first configured channel" but "say nothing
 * at all" — and a test pins that.
 */
class SilentChannel implements NudgeChannel
{
    public function canReach(User $user): bool
    {
        return false;
    }

    public function deliver(User $user, Nudge $nudge): void
    {
        //
    }
}
