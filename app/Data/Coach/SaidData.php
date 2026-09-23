<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

/**
 * A student's own sentence and the day they said it, unaltered.
 */
class SaidData extends Data
{
    public function __construct(
        public string $said_on,
        public string $content,
    ) {}
}
