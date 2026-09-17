<?php

namespace App\Ai\Tools;

use App\Enums\GapType;
use App\Models\Gap;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What is already known to be in this student's way.
 *
 * Returns closed gaps as well as open ones, and that is deliberate: a student
 * who closed an access gap last spring should not be asked about it again as
 * though nothing happened. Continuity across months is the thing a coach with
 * no memory cannot fake.
 */
class GetGapsTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get the gaps already identified for this student, open and closed. Call this before deciding what to work on, so you do not reopen something they have already dealt with.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $gaps = $this->user->gaps()->latest()->get();

        if ($gaps->isEmpty()) {
            return json_encode([
                'open' => [],
                'closed' => [],
                'unexplored' => GapType::values(),
                'guidance' => 'Nothing has been identified yet. Ask about their situation before naming a gap.',
            ]);
        }

        $format = fn (Gap $gap) => array_filter([
            'type' => $gap->type->value,
            'status' => $gap->status->value,
            'summary' => $gap->summary,
            'they_said' => $gap->evidence,
            'closed_at' => $gap->closed_at?->toDateString(),
        ], fn ($value) => $value !== null);

        $active = $gaps->filter(fn (Gap $gap) => $gap->isActive());

        return json_encode([
            'open' => $active->map($format)->values(),
            'closed' => $gaps->reject(fn (Gap $gap) => $gap->isActive())->map($format)->values(),
            'unexplored' => array_values(array_diff(
                GapType::values(),
                $gaps->map(fn (Gap $gap) => $gap->type->value)->all(),
            )),
            'guidance' => 'Work on what is open. Do not reopen what is closed unless they raise it themselves.',
        ]);
    }
}
