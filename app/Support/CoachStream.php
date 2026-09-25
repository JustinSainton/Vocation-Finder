<?php

namespace App\Support;

use App\Ai\Agents\PathwayCoachAgent;
use App\Data\Coach\CoachActionData;
use App\Data\Coach\CoachSettledData;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One coach turn as server-sent events.
 *
 * Four event types, one JSON object per `data:` line:
 *
 * - `status` — what the coach is doing while it is not yet talking, named in
 *   the student's terms ("Rereading your answers"), never as a tool name.
 * - `delta` — the next piece of what it is saying.
 * - `done` — the turn as persisted, the current step, and fresh starters.
 * - `error` — the turn failed; the student's own words are already kept.
 *
 * The SDK persists both sides of the turn when the stream finishes, so `done`
 * is read back from the store rather than assembled here.
 */
class CoachStream
{
    /**
     * @param  Closure(): StreamableAgentResponse  $start
     * @param  (Closure(): void)|null  $after  runs once the turn is persisted
     * @param  (Closure(): ?string)|null  $recover  words to say instead when the model fails before saying anything
     * @param  (Closure(): void)|null  $finally  runs however the stream ends
     */
    public function respond(
        User $user,
        PathwayCoachAgent $agent,
        Closure $start,
        ?Closure $after = null,
        ?Closure $recover = null,
        ?Closure $finally = null,
        string $firstStatus = 'Reading what you said',
    ): StreamedResponse {
        return response()->stream(function () use ($user, $agent, $start, $after, $recover, $finally, $firstStatus) {
            try {
                $this->turn($user, $agent, $start, $after, $recover, $firstStatus);
            } finally {
                if ($finally) {
                    $finally();
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function turn(User $user, PathwayCoachAgent $agent, Closure $start, ?Closure $after, ?Closure $recover, string $firstStatus): void
    {
        $this->emit(['type' => 'status', 'label' => $firstStatus]);

        $text = '';

        try {
            foreach ($start() as $event) {
                if ($event instanceof TextDelta) {
                    $text .= $event->delta;
                    $this->emit(['type' => 'delta', 'text' => $event->delta]);
                } elseif ($event instanceof ToolCall) {
                    $this->emit(['type' => 'status', 'label' => static::label($event->toolCall->name)]);
                }
            }

            if (trim($text) === '') {
                Log::warning('coach_turn_produced_no_text', ['user_id' => $user->id, 'streamed' => true]);
                $this->emit(['type' => 'status', 'label' => 'Putting it into words']);
                $this->emit(['type' => 'delta', 'text' => (string) $agent->speakAfterSilence()->text]);
            }
        } catch (Throwable $exception) {
            Log::error('pathway coach stream failed', ['user_id' => $user->id, 'error' => $exception->getMessage()]);

            $recovered = trim($text) === '' && $recover ? $recover() : null;

            if ($recovered === null) {
                $this->emit(['type' => 'error', 'message' => 'Something went wrong. What you wrote is kept — try again.']);

                return;
            }

            $this->emit(['type' => 'delta', 'text' => $recovered]);
        }

        if ($after) {
            $after();
        }

        $this->emit(['type' => 'done'] + static::settled($user)->toArray());
    }

    /**
     * What every client needs after a turn: the thread as stored, the one
     * step in progress, and what to offer next.
     */
    public static function settled(User $user): CoachSettledData
    {
        return new CoachSettledData(
            items: (new CoachThread)->items($user),
            current_action: CoachActionData::optional((new ActionQueue)->current($user)),
            starters: (new CoachStarters)->for($user),
        );
    }

    /**
     * The coach's work, in the student's words.
     */
    public static function label(string $tool): string
    {
        $name = strtolower($tool);

        return match (true) {
            str_contains($name, 'pathwayprofile') => 'Reading your portrait',
            str_contains($name, 'assessmentresponses') => 'Rereading your answers',
            str_contains($name, 'signals') => 'Pulling out what stood out',
            str_contains($name, 'searchbrain') => 'Looking through what you have said before',
            str_contains($name, 'savetobrain') => 'Keeping that in your brain',
            str_contains($name, 'gap') => 'Naming what is in the way',
            str_contains($name, 'assignaction') => 'Setting your next step',
            str_contains($name, 'currentaction') => 'Checking your current step',
            str_contains($name, 'readiness') => 'Checking where you are',
            str_contains($name, 'habit') => 'Looking at your habits',
            str_contains($name, 'plan'), str_contains($name, 'milestone') => 'Checking your plan',
            str_contains($name, 'locker') => 'Opening your locker',
            default => 'Thinking it through',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emit(array $payload): void
    {
        echo 'data: '.json_encode($payload)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
