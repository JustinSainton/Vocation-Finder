<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\ResumeCoachAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResumeConversationController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $agent = new ResumeCoachAgent($user);
            $response = $agent->forUser($user)->prompt(
                'I\'d like help building my resume. Can you start by asking me about my background?'
            );

            return response()->json([
                'conversation_id' => $response->conversationId(),
                'message' => $response->text(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Resume coach start failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The resume coach is temporarily unavailable. Please try again.'], 503);
        }
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        try {
            $agent = new ResumeCoachAgent($user);
            $response = $agent
                ->continue($validated['conversation_id'], as: $user)
                ->prompt($validated['message']);

            return response()->json([
                'conversation_id' => $validated['conversation_id'],
                'message' => $response->text(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Resume coach message failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 503);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $conversationId = $request->query('conversation_id');

        if (! $conversationId) {
            return response()->json(['messages' => []]);
        }

        $user = $request->user();

        $agent = new ResumeCoachAgent($user);
        $messages = $agent->conversationHistory($conversationId, as: $user);

        return response()->json(['messages' => $messages]);
    }
}
