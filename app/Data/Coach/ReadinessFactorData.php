<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

class ReadinessFactorData extends Data
{
    public function __construct(
        public string $label,
        public string $standing,
        public string $move,
    ) {}
}
