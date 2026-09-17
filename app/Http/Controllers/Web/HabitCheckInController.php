<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Support\HabitTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The student answering for one occasion.
 *
 * Checking in is the student's act and nobody else's — there is no route by
 * which the coach, a counsellor or a parent records that a habit happened.
 * A tracker someone else can fill in is a compliance report.
 */
class HabitCheckInController extends Controller
{
    public function store(Request $request, Habit $habit): RedirectResponse
    {
        abort_unless($habit->user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'happened' => ['required', 'boolean'],
            /**
             * A note is never required, including on a miss. Requiring a
             * reason to say "no" is how a form teaches someone to stop
             * answering, and silence is the one state the coach can do
             * nothing with.
             */
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        (new HabitTracker)->checkIn($habit, $validated['happened'], $validated['note'] ?? null);

        return back();
    }
}
