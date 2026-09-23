<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\PathwayCoachAgent;
use App\Http\Controllers\Controller;
use App\Support\AccessPolicy;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\BrainstormSchedule;
use App\Support\CoachOpening;
use App\Support\CoachStarters;
use App\Support\CoachStream;
use App\Support\CoachThread;
use App\Support\ConversationLocale;
use App\Support\CrisisCheck;
use App\Support\HabitTracker;
use App\Support\ReadinessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PathwayCoachController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! AccessPolicy::canUseCoach($user)) {
            return response()->json(['message' => AccessPolicy::coachBlockedReason($user)], 403);
        }

        return response()->json([
            'current_action' => (new ActionQueue)->current($user)?->only(['id', 'title', 'rationale']),
            'readiness' => (new ReadinessCalculator)->explain($user),
            'habits' => (new HabitTracker)->forStudent($user),
            'invitation' => (new BrainstormSchedule)->invitation($user),
            'starters' => (new CoachStarters)->for($user),
            'opening' => (new CoachOpening)->due($user),
        ]);
    }

    /**
     * The thread as the student should see it: internal prompts and tool-only
     * rows removed, steps interleaved where they were assigned.
     *
     * `messages` keeps its original `{role, content}` shape for clients that
     * predate `items`.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! AccessPolicy::canUseCoach($user)) {
            return response()->json(['message' => AccessPolicy::coachBlockedReason($user)], 403);
        }

        $items = (new CoachThread)->items($user);

        return response()->json([
            'messages' => array_values(array_map(
                fn (array $item) => ['id' => $item['id'], 'role' => $item['role'], 'content' => $item['content'], 'at' => $item['at']],
                array_filter($items, fn (array $item) => $item['type'] === 'message'),
            )),
            'items' => $items,
        ]);
    }

    /**
     * The coach speaks first. Not streamed: mobile shows a thinking state and
     * receives the whole opener, which keeps the client on plain `fetch`.
     */
    public function open(Request $request): JsonResponse
    {
        $user = $request->user();
        $opening = new CoachOpening;
        $kind = $opening->due($user);

        try {
            $agent = new PathwayCoachAgent($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $lock = Cache::lock("coach-opening:{$user->id}", 120);

        if ($kind === null || ! $lock->get()) {
            return response()->json(['message' => null] + CoachStream::settled($user));
        }

        try {
            $text = (string) $agent->open($kind)->text;
        } catch (Throwable $exception) {
            Log::error('pathway coach opening failed', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
            $text = $opening->fallback($user, $kind);
            $opening->recordFallback($user, $kind, $text);
        } finally {
            $lock->release();
        }

        return response()->json(['message' => $text] + CoachStream::settled($user));
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        if ((new CrisisCheck)->standing($validated['message'])->isEscalation()) {
            (new BrainCapture)->captureCoachTurn($user, role: 'user', content: $validated['message']);

            return response()->json([
                'support' => (new CrisisCheck)->support(
                    ConversationLocale::normalize($user->assessments()->latest()->value('locale')),
                ),
            ]);
        }

        try {
            $agent = new PathwayCoachAgent($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        try {
            $response = $agent->respondTo($validated['message']);
        } catch (Throwable $exception) {
            Log::error('pathway coach message failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 503);
        }

        (new BrainstormSchedule)->attended($user);

        return response()->json(['message' => (string) $response->text] + CoachStream::settled($user));
    }
}
