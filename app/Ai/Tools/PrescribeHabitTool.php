<?php

namespace App\Ai\Tools;

use App\Enums\HabitCadence;
use App\Models\Gap;
use App\Models\User;
use App\Support\HabitTracker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

/**
 * Give the student something to do repeatedly, aimed at a gap.
 *
 * `gap_id` is required and is validated against this student's own gaps. A
 * habit with no gap behind it is a generic suggestion wearing a personalised
 * label, which is the exact thing roadmap 1.5 asks this not to be — so the
 * constraint lives in the signature and the schema rather than in a sentence
 * of the prompt a model can drift away from.
 *
 * Refusals come back as guidance, not errors, for the same reason they do in
 * {@see AssignActionTool}: the model is the thing that has to change.
 */
class PrescribeHabitTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Give this student one repeated thing to do, aimed at a gap you have already recorded. A habit, not a step: something small enough to do again on an ordinary day.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The repeated thing, written to them in one sentence. One habit, not several.')
                ->required(),
            'gap_id' => $schema
                ->string()
                ->description('The id of the gap this habit serves. Required — call GetGapsTool first.')
                ->required(),
            'cadence' => $schema
                ->string()
                ->enum(HabitCadence::values())
                ->description('How often it comes round: daily, weekdays (school days), or weekly')
                ->required(),
            'why' => $schema
                ->string()
                ->description('Why this one, in their terms — what it would change')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $gap = Gap::query()
            ->where('user_id', $this->user->id)
            ->find(trim((string) $request['gap_id']));

        if (! $gap) {
            return json_encode([
                'prescribed' => false,
                'guidance' => 'That is not one of this student\'s gaps. Call GetGapsTool, record the gap this habit is for, and use its id. A habit with no gap behind it is a generic suggestion.',
            ]);
        }

        $cadence = HabitCadence::tryFrom((string) $request['cadence']);

        if (! $cadence) {
            return json_encode([
                'prescribed' => false,
                'guidance' => 'Cadence must be one of: '.implode(', ', HabitCadence::values()).'.',
            ]);
        }

        try {
            $habit = (new HabitTracker)->prescribe(
                $this->user,
                $gap,
                (string) $request['title'],
                $cadence,
                trim((string) $request['why']) ?: null,
            );
        } catch (RuntimeException $exception) {
            return json_encode([
                'prescribed' => false,
                'guidance' => $exception->getMessage(),
            ]);
        }

        return json_encode([
            'prescribed' => true,
            'habit_id' => $habit->id,
            'title' => $habit->title,
            'cadence' => $habit->cadence->label(),
            'guidance' => 'Tell them what it is and when it happens, then stop. Do not stack a second habit on top of it in the same conversation.',
        ]);
    }
}
