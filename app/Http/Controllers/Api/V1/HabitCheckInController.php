<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Support\HabitTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HabitCheckInController extends Controller
{
    public function store(Request $request, Habit $habit): JsonResponse
    {
        abort_unless($habit->user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'happened' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        (new HabitTracker)->checkIn($habit, $validated['happened'], $validated['note'] ?? null);

        return response()->json(['answered_today' => true], 201);
    }
}
