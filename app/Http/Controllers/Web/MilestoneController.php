<?php

namespace App\Http\Controllers\Web;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The student moves their own milestone.
 *
 * As with a habit check-in, there is deliberately no route by which a coach,
 * counsellor or parent marks a student's milestone — a plan somebody else can
 * update is a progress report with the student's name on it.
 *
 * `put_down` is offered as plainly as `done`. Deciding not to sit the SAT is a
 * decision, and a plan that only lets you finish things teaches a student that
 * changing their mind is a failure state.
 */
class MilestoneController extends Controller
{
    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        abort_unless($milestone->user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MilestoneStatus::class)],
        ]);

        $milestone->update(['status' => MilestoneStatus::from($validated['status'])]);

        return back();
    }
}
