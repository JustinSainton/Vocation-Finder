<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\ActionQueue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What the student is already meant to be doing.
 *
 * A coach that cannot see this will assign a second step on top of the first,
 * which is how a queue quietly turns back into a list.
 */
class GetCurrentActionTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Check whether this student already has an action in progress, and what they have finished before. Always call this before assigning anything.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $current = (new ActionQueue)->current($this->user);

        $settled = $this->user->actions()
            ->whereNot('status', 'active')
            ->latest('settled_at')
            ->limit(5)
            ->get()
            ->map(fn ($action) => array_filter([
                'title' => $action->title,
                'status' => $action->status->value,
                'they_said' => $action->reflection,
            ], fn ($value) => $value !== null));

        if (! $current) {
            return json_encode([
                'has_action' => false,
                'previous' => $settled,
                'guidance' => 'They have nothing in progress. Once you know which gap is live, give them one step aimed at it.',
            ]);
        }

        return json_encode([
            'has_action' => true,
            'title' => $current->title,
            'rationale' => $current->rationale,
            'assigned_on' => $current->assigned_at?->toDateString(),
            'previous' => $settled,
            'guidance' => 'They already have a step in progress. Ask how it went. Do not assign another one on top of it.',
        ]);
    }
}
