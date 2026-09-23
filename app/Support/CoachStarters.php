<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Things a student can say to the coach with one tap, drawn from their own
 * portrait.
 *
 * A blank box is the decision friction this product exists to remove, so the
 * first move is offered rather than demanded. Every starter is a sentence the
 * student could plausibly say themselves, in the first person, and none of
 * them is an answer — they are ways in. Four at most: a menu of ten is the
 * list of options the coach is forbidden to hand over.
 */
class CoachStarters
{
    public const MAX = 4;

    /**
     * @return list<string>
     */
    public function for(User $user): array
    {
        $starters = [];

        if ((new ActionQueue)->current($user)) {
            $starters[] = 'Here is how my step went.';
        }

        $profile = $user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first()
            ?->vocationalProfile;

        if ($profile) {
            $direction = collect($profile->primary_pathways ?? [])
                ->map(fn ($pathway) => trim((string) $pathway))
                ->first(fn (string $pathway) => $pathway !== '' && mb_strlen($pathway) <= 40)
                ?? (filled($profile->primary_domain) && mb_strlen((string) $profile->primary_domain) <= 40 ? $profile->primary_domain : null);

            if ($direction) {
                $starters[] = 'What would testing '.Str::lcfirst($direction).' look like this month?';
            }

            if (filled($profile->missing_evidence)) {
                $starters[] = 'What is my portrait still missing?';
            }
        }

        $starters[] = AccessPolicy::isAdult($user)
            ? 'Who should I talk to first about this?'
            : 'How do I bring my parents into this?';

        $starters[] = 'I am not sure this fits me.';

        return array_values(array_slice(array_unique($starters), 0, self::MAX));
    }
}
