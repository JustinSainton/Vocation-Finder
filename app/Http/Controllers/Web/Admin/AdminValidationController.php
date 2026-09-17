<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Support\ValidationReadout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roadmap 5.2 — the validation read-out, for us.
 *
 * Behind `admin`, and deliberately not a student-facing or counsellor-facing
 * surface. These numbers are about whether the product works; a student shown
 * them is being handed the company's self-assessment in place of their own
 * result, and a counsellor shown them starts managing the metric.
 */
class AdminValidationController extends Controller
{
    public function __construct(protected ValidationReadout $readout = new ValidationReadout) {}

    public function index(Request $request): Response
    {
        $days = (int) $request->integer('days', 90);

        return Inertia::render('Admin/Validation', [
            'days' => $days,
            'readout' => $this->readout->for($days > 0 ? now()->subDays($days) : null),
        ]);
    }
}
