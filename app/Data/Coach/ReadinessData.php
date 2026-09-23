<?php

namespace App\Data\Coach;

use App\Support\ReadinessCalculator;
use Spatie\LaravelData\Data;

/**
 * Where a student stands, in words, as {@see ReadinessCalculator::explain()} describes it.
 */
class ReadinessData extends Data
{
    /**
     * @param  array<string, ReadinessFactorData>  $factors  keyed by readiness factor
     * @param  list<ReadinessPointData>  $history
     */
    public function __construct(
        public string $level,
        public string $level_label,
        public string $level_description,
        public string $what_moves_it,
        public array $factors,
        public array $history,
    ) {}
}
