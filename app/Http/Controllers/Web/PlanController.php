<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentPlace;
use App\Http\Controllers\Controller;
use App\Support\StudentPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plan — the widest view the product offers.
 *
 * Everything on it is computed at request time: the sections from the school
 * calendar and the student's grade, the passages from the sections, and
 * whether a window has closed from today's date. Nothing here is a stored
 * verdict about the student, so there is nothing to go stale in September and
 * nothing to correct when a grade level changes.
 */
class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Plan/Index', [
            'blurb' => StudentPlace::Plan->blurb(),
            ...(new StudentPlan)->for($request->user()),
        ]);
    }
}
