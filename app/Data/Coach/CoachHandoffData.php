<?php

namespace App\Data\Coach;

use App\Support\CoachHandoff;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * The door at the end of the results page, from {@see CoachHandoff}.
 */
class CoachHandoffData extends Data
{
    /**
     * @param  list<string>  $starters
     */
    public function __construct(
        #[LiteralTypeScriptType("'open' | 'account' | 'consent' | 'checkout' | 'later'")]
        public string $state,
        public string $eyebrow,
        public string $headline,
        public string $body,
        public ?string $href,
        public ?string $cta,
        public array $starters,
    ) {}
}
