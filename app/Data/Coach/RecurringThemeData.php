<?php

namespace App\Data\Coach;

use App\Support\ThresholdSurfacing;
use Spatie\LaravelData\Data;

/**
 * Something the student keeps coming back to, from {@see ThresholdSurfacing::detect()}.
 */
class RecurringThemeData extends Data
{
    /**
     * @param  list<SaidData>  $in_their_words
     */
    public function __construct(
        public string $term,
        public int $entry_count,
        public string $first_said_on,
        public string $last_said_on,
        public array $in_their_words,
        public bool $needs_human,
    ) {}
}
