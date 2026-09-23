<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * A step, marked in the thread where it was assigned.
 */
class CoachThreadStepData extends Data
{
    #[LiteralTypeScriptType("'step'")]
    public string $type = 'step';

    public function __construct(
        public string $id,
        public string $title,
        public ?string $rationale,
        #[LiteralTypeScriptType("'active' | 'completed' | 'skipped'")]
        public string $status,
        public string $at,
    ) {}
}
