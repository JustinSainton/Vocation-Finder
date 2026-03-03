<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\User;

class GuestUpgradeService
{
    public function upgrade(User $user, ?string $guestToken): int
    {
        if (! $guestToken) {
            return 0;
        }

        return Assessment::where('guest_token', $guestToken)
            ->whereNull('user_id')
            ->update([
                'user_id' => $user->id,
                'guest_token' => null,
            ]);
    }
}
