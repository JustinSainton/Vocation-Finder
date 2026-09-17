<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\FirstRunSequence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What happens next, and nothing else.
 *
 * The sequence ends at one assigned action: "if a student finishes their first
 * session without one concrete thing to do, we haven't proven anything."
 */
class FirstRunController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('FirstRun/Show', [
            'firstRun' => FirstRunSequence::toArray($request->user()),
        ]);
    }
}
