<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * One captured readiness level. `because` is omitted, not null, when the
 * snapshot recorded no reason.
 */
class ReadinessPointData extends Data
{
    public function __construct(
        public string $on,
        public string $level,
        public Optional|string $because,
    ) {}
}
