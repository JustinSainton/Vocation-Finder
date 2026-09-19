<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Support\ActionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function complete(Request $request, Action $action): JsonResponse
    {
        abort_unless($action->user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'reflection' => ['nullable', 'string', 'max:2000'],
        ]);

        (new ActionQueue)->complete($action, $validated['reflection'] ?? null);

        return response()->json([
            'current_action' => (new ActionQueue)->current($request->user())?->only(['id', 'title', 'rationale']),
        ]);
    }

    public function skip(Request $request, Action $action): JsonResponse
    {
        abort_unless($action->user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        (new ActionQueue)->skip($action, $validated['reason'] ?? null);

        return response()->json([
            'current_action' => (new ActionQueue)->current($request->user())?->only(['id', 'title', 'rationale']),
        ]);
    }
}
