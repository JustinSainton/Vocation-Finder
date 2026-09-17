<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentPlace;
use App\Http\Controllers\Controller;
use App\Support\HabitTracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Habits get their own place rather than a section on the coach page.
 *
 * The coach still shows them, because the check-in has to be answerable in
 * the moment the student is already there. This page is for the other
 * question — how the thing is actually going — and it is the only surface
 * that shows a habit the student has paused or retired, so setting one down
 * is visibly a move rather than a disappearance.
 */
class HabitController extends Controller
{
    public function index(Request $request): Response
    {
        $tracker = new HabitTracker;

        return Inertia::render('Habits/Index', [
            'blurb' => StudentPlace::Habits->blurb(),
            'habits' => $tracker->forStudent($request->user()),
        ]);
    }
}
