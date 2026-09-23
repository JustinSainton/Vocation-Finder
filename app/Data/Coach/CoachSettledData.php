<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

/**
 * A coach turn once it is persisted, streamed or not.
 */
class CoachSettledData extends Data
{
    /**
     * @param  list<CoachThreadMessageData|CoachThreadStepData>  $items
     * @param  list<string>  $starters
     */
    public function __construct(
        public array $items,
        public ?CoachActionData $current_action,
        public array $starters,
    ) {}
}
