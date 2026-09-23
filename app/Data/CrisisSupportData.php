<?php

namespace App\Data;

use App\Support\CrisisCheck;
use Spatie\LaravelData\Data;

/**
 * The fixed support message {@see CrisisCheck::support()} returns
 * instead of coaching.
 */
class CrisisSupportData extends Data
{
    /**
     * @param  list<string>  $body
     * @param  list<CrisisResourceData>  $resources
     */
    public function __construct(
        public string $heading,
        public array $body,
        public array $resources,
    ) {}
}
