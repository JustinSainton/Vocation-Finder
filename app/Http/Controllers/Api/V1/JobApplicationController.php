<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Services\ApplicationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function __construct(
        private ApplicationTrackingService $tracking,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->jobApplications()->with('jobListing:id,title,company_name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $applications = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($applications);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_listing_id' => ['nullable', 'uuid', 'exists:job_listings,id'],
            'company_name' => ['required_without:job_listing_id', 'string', 'max:255'],
            'job_title' => ['required_without:job_listing_id', 'string', 'max:255'],
            'job_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        // Auto-fill from job listing if provided
        if (! empty($validated['job_listing_id'])) {
            $listing = JobListing::find($validated['job_listing_id']);
            $validated['company_name'] = $validated['company_name'] ?? $listing->company_name;
            $validated['job_title'] = $validated['job_title'] ?? $listing->title;
            $validated['job_url'] = $validated['job_url'] ?? $listing->source_url;
            $validated['source'] = $validated['source'] ?? $listing->source;
        }

        $application = JobApplication::create([
            'user_id' => $request->user()->id,
            ...$validated,
            'status' => 'saved',
        ]);

        $this->tracking->logEvent($application, 'created');

        return response()->json(['data' => $application->load('events')], 201);
    }

    public function show(Request $request, JobApplication $jobApplication): JsonResponse
    {
        if ($jobApplication->user_id !== $request->user()->id) {
            abort(403);
        }

        $jobApplication->load(['events', 'jobListing:id,title,company_name,source_url', 'resumeVersion:id,status,quality_score', 'coverLetter:id,status']);

        return response()->json(['data' => $jobApplication]);
    }

    public function update(Request $request, JobApplication $jobApplication): JsonResponse
    {
        if ($jobApplication->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'in:' . implode(',', JobApplication::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_date' => ['nullable', 'date'],
            'salary_offered' => ['nullable', 'integer'],
            'resume_version_id' => ['nullable', 'uuid', 'exists:resume_versions,id'],
            'cover_letter_id' => ['nullable', 'uuid', 'exists:cover_letters,id'],
        ]);

        // Handle status transition separately
        if (isset($validated['status']) && $validated['status'] !== $jobApplication->status) {
            $jobApplication = $this->tracking->transitionStatus(
                $jobApplication,
                $validated['status']
            );
            unset($validated['status']);
        }

        // Update other fields
        $updatableFields = array_filter($validated, fn ($v) => $v !== null);
        if (! empty($updatableFields)) {
            $jobApplication->update($updatableFields);
        }

        return response()->json(['data' => $jobApplication->load('events')]);
    }

    public function destroy(Request $request, JobApplication $jobApplication): JsonResponse
    {
        if ($jobApplication->user_id !== $request->user()->id) {
            abort(403);
        }

        $jobApplication->delete();

        return response()->json(null, 204);
    }

    public function logEvent(Request $request, JobApplication $jobApplication): JsonResponse
    {
        if ($jobApplication->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:50'],
            'details' => ['nullable', 'array'],
        ]);

        $event = $this->tracking->logEvent(
            $jobApplication,
            $validated['event_type'],
            $validated['details'] ?? null
        );

        return response()->json(['data' => $event], 201);
    }

    public function analytics(Request $request): JsonResponse
    {
        $analytics = $this->tracking->getAnalytics($request->user()->id);

        return response()->json($analytics);
    }
}
