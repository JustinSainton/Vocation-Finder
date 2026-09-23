<?php

namespace App\Data\Coach;

use App\Models\User;
use App\Support\ActionQueue;
use App\Support\BrainstormSchedule;
use App\Support\CoachOpening;
use App\Support\CoachStarters;
use App\Support\HabitTracker;
use App\Support\ReadinessCalculator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Everything around the conversation: the current step, where the student
 * stands, their habits, any return invitation, and whether the coach should
 * speak first.
 */
class CoachStateData extends Data
{
    /**
     * @param  list<HabitData>  $habits
     * @param  list<string>  $starters
     */
    public function __construct(
        public ?CoachActionData $current_action,
        public ReadinessData $readiness,
        public array $habits,
        public ?BrainstormInvitationData $invitation,
        public array $starters,
        #[LiteralTypeScriptType("'first' | 'returning' | null")]
        public ?string $opening,
    ) {}

    public static function for(User $user): self
    {
        return new self(
            current_action: CoachActionData::optional((new ActionQueue)->current($user)),
            readiness: ReadinessData::from((new ReadinessCalculator)->explain($user)),
            habits: HabitData::collect((new HabitTracker)->forStudent($user)),
            invitation: BrainstormInvitationData::optional((new BrainstormSchedule)->invitation($user)),
            starters: (new CoachStarters)->for($user),
            opening: (new CoachOpening)->due($user),
        );
    }
}
