<?php

namespace App\Data\Coach;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Something the student or the coach said, as it is stored.
 */
class CoachThreadMessageData extends Data
{
    #[LiteralTypeScriptType("'message'")]
    public string $type = 'message';

    public function __construct(
        public string $id,
        #[LiteralTypeScriptType("'user' | 'assistant'")]
        public string $role,
        public string $content,
        public string $at,
    ) {}
}
