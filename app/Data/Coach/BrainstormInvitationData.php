<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

class BrainstormInvitationData extends Data
{
    public function __construct(
        public ?RecurringThemeData $opens_with,
        public string $prompt,
    ) {}
}
