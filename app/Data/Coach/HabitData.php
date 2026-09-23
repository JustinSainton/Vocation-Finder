<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;

/**
 * A habit as the student is shown it: words and a move, never the counts.
 */
class HabitData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $why,
        public string $cadence,
        public string $standing,
        public string $meaning,
        public string $next_move,
        public bool $answered_today,
    ) {}
}
