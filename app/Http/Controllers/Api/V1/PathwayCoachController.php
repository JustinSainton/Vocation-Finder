<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\PathwayCoachAgent;
use App\Http\Controllers\Controller;
use App\Support\AccessPolicy;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\BrainstormSchedule;
use App\Support\ConversationLocale;
use App\Support\CrisisCheck;
use App\Support\HabitTracker;
use App\Support\ReadinessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! AccessPolicy::canUseCoach($user)) {
            return response()->json(['message' => AccessPolicy::coachBlockedReason($user)], 403);
        }

        try {
            $agent = new PathwayCoachAgent($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $messages = [];
        foreach ($agent->continueLastConversation($user)->messages() as $message) {
            $messages[] = [
                'role' => is_array($message) ? ($message['role'] ?? 'assistant') : ($message->role ?? 'assistant'),
                'content' => is_array($message) ? ($message['content'] ?? '') : (isset($message->content) ? (string) $message->content : (string) $message),
            ];
        }

        return response()->json(['messages' => $messages]);
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
        } catch (\Throwable $exception) {
            Log::error('pathway coach message failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 503);
        }

        (new BrainstormSchedule)->attended($user);

        return response()->json(['message' => (string) $response->text]);
    }
}
