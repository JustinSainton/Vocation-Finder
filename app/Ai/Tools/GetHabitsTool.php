<?php

namespace App\Ai\Tools;

use App\Models\Habit;
use App\Models\User;
use App\Support\HabitTracker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What this student is already doing again and again, and how it is going.
 *
 * The counts travel with the standing here — unlike the student's own view,
 * which gets words. The coach needs to know that four of the last fourteen
 * days happened in order to decide the habit is the wrong size; the student
 * needs to hear that it is taking hold and which days to build around.
 */
class GetHabitsTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get the habits this student is already keeping and how each one is actually going. Always call this before prescribing a new habit.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $tracker = new HabitTracker;

        $habits = $tracker->active($this->user)->map(function (Habit $habit) use ($tracker) {
            $standing = $tracker->standing($habit);

            return [
                'habit_id' => $habit->id,
                'title' => $habit->title,
                'cadence' => $habit->cadence->label(),
                'gap_id' => $habit->gap_id,
                'started_on' => $habit->started_at?->toDateString(),
                'standing' => $standing['standing']->value,
                'what_that_means' => $standing['standing']->meaning(),
                'next_move' => $standing['standing']->nextMove(),
                'occasions_expected' => $standing['expected'],
                'occasions_kept' => $standing['kept'],
                'misses_they_explained' => $standing['explained'],
            ];
        })->values();

        return json_encode([
            'habits' => $habits,
            'guidance' => $habits->isEmpty()
                ? 'They are not keeping any habits yet. A habit is only worth prescribing once you know which gap it serves.'
                : 'A stalled habit is the wrong size, not a failure of will. Make it smaller or set it down — do not tell them to try harder, and never count their misses back at them.',
        ]);
    }
}
