<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\ReadinessCalculator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Where this student stands and what would move it.
 *
 * The coach needs this because readiness is the answer to why a student comes
 * back voluntarily — "that's the difference between compliance usage and
 * voluntary usage." A coach that cannot see it will either ignore the metric
 * or invent a reason to return, and an invented reason is a gimmick the
 * student will see through immediately.
 *
 * Returns no numbers. There are none to return.
 */
class GetReadinessTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'See how ready this student is to move, which parts of that are strong or thin, what single thing would move it, and how it has changed over time.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $readiness = (new ReadinessCalculator)->explain($this->user);

        return json_encode([
            ...$readiness,
            'guidance' => 'Readiness is something they work at, not something they are. Never present it as a score or a rank, and never tell them it went down. If they ask what would move it, give them the one move above — not the whole list.',
        ]);
    }
}
