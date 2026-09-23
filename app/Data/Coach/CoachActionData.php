<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

/**
 * The one step a student is working on, as every client is given it.
 */
class CoachActionData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $rationale,
    ) {}
}
