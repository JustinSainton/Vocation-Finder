<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ApplicationTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationTrackingService $tracking,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = $user->jobApplications()
            ->with('jobListing:id,title,company_name');

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('Applications/Index', [
            'applications' => $query->orderByDesc('updated_at')->paginate(20)->withQueryString(),
            'analytics' => $this->tracking->getAnalytics($user->id),
            'currentFilter' => $status,
        ]);
    }

    public function show(Request $request, \App\Models\JobApplication $jobApplication): Response
    {
        if ($jobApplication->user_id !== $request->user()->id) {
            abort(403);
        }

        $jobApplication->load(['events', 'jobListing', 'resumeVersion', 'coverLetter']);

        return Inertia::render('Applications/Show', [
            'application' => $jobApplication,
        ]);
    }
}
