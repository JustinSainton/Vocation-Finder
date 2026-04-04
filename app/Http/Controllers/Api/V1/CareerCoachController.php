<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\CareerCoachAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerCoachController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $agent = new CareerCoachAgent($user);
        $response = $agent->forUser($user)->prompt(
            'I\'d like to explore career options that align with my vocational calling. Can you help?'
        );

        return response()->json([
            'conversation_id' => $response->conversationId(),
            'message' => $response->text(),
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        $agent = new CareerCoachAgent($user);
        $response = $agent
            ->continue($validated['conversation_id'], as: $user)
            ->prompt($validated['message']);

        return response()->json([
            'conversation_id' => $validated['conversation_id'],
            'message' => $response->text(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $conversationId = $request->query('conversation_id');

        if (! $conversationId) {
            return response()->json(['messages' => []]);
        }

        $user = $request->user();
        $agent = new CareerCoachAgent($user);
        $messages = $agent->conversationHistory($conversationId, as: $user);

        return response()->json(['messages' => $messages]);
    }
}
