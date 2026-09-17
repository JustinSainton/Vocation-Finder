<?php

namespace App\Ai\Tools;

use App\Models\Gap;
use App\Models\User;
use App\Support\ActionQueue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

/**
 * Give the student the one thing they are doing next.
 *
 * Every refusal here comes back as guidance rather than an error, because the
 * model is the thing that has to change its behaviour. "That is a list" told
 * plainly produces a single action on the next turn; a stack trace produces
 * an apology and another list.
 */
class AssignActionTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Give this student the single next step they are going to take. One thing, in one sentence, that they could realistically do this week. Only one action can be in progress at a time.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The one thing to do, written to them in one sentence. Not a list, not a plan.')
                ->required(),
            'rationale' => $schema
                ->string()
                ->description('Why this step, in their terms — what it would tell them')
                ->required(),
            'gap_id' => $schema
                ->string()
                ->description('The id of the gap this step is aimed at, or an empty string if none')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $gapId = trim((string) $request['gap_id']);

        $gap = $gapId !== ''
            ? Gap::query()->where('user_id', $this->user->id)->find($gapId)
            : null;

        try {
            $action = (new ActionQueue)->assign(
                $this->user,
                (string) $request['title'],
                trim((string) $request['rationale']) ?: null,
                $gap,
            );
        } catch (RuntimeException $exception) {
            return json_encode([
                'assigned' => false,
                'guidance' => $exception->getMessage(),
            ]);
        }

        return json_encode([
            'assigned' => true,
            'action_id' => $action->id,
            'title' => $action->title,
            'guidance' => 'This is now their one action. Do not add to it. Tell them what it is and stop.',
        ]);
    }
}
