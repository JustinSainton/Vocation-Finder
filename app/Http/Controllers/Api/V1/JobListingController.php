<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Services\Jobs\JobMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobListingController extends Controller
{
    public function __construct(
        private JobMatchingService $matching,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = JobListing::active()->classified()->with('vocationalCategories');

        // Filter by pathway/category
        if ($pathway = $request->query('pathway')) {
            $query->whereHas('vocationalCategories', fn ($q) => $q->where('slug', $pathway));
        }

        // Filter by remote
        if ($request->has('remote')) {
            $query->where('is_remote', filter_var($request->query('remote'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by salary
        if ($min = $request->query('salary_min')) {
            $query->where(fn ($q) => $q->whereNull('salary_max')->orWhere('salary_max', '>=', (int) $min));
        }

        // Filter by location
        if ($location = $request->query('location')) {
            $query->where('location', 'like', "%{$location}%");
        }

        // Search by keyword
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('description_plain', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('posted_at');

        $jobs = $query->paginate($request->query('per_page', 20));

        // Attach match scores for authenticated users
        $user = $request->user();

        $jobs->getCollection()->transform(function (JobListing $job) use ($user) {
            $data = [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'company_url' => $job->company_url,
                'location' => $job->location,
                'is_remote' => $job->is_remote,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'source' => $job->source,
                'source_url' => $job->source_url,
                'posted_at' => $job->posted_at?->toISOString(),
                'categories' => $job->vocationalCategories->map(fn ($c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'relevance' => $c->pivot->relevance_score,
                ]),
            ];

            if ($user) {
                $score = $this->matching->scoreJob($job, $user);
                $data['match_score'] = $score;
                $data['match_percent'] = (int) round($score * 100);
            }

            return $data;
        });

        return response()->json($jobs);
    }

    public function show(Request $request, JobListing $jobListing): JsonResponse
    {
        $jobListing->load('vocationalCategories');

        $data = [
            'id' => $jobListing->id,
            'title' => $jobListing->title,
            'company_name' => $jobListing->company_name,
            'company_url' => $jobListing->company_url,
            'location' => $jobListing->location,
            'is_remote' => $jobListing->is_remote,
            'salary_min' => $jobListing->salary_min,
            'salary_max' => $jobListing->salary_max,
            'salary_currency' => $jobListing->salary_currency,
            'description' => $jobListing->description,
            'required_skills' => $jobListing->required_skills,
            'source' => $jobListing->source,
            'source_url' => $jobListing->source_url,
            'posted_at' => $jobListing->posted_at?->toISOString(),
            'categories' => $jobListing->vocationalCategories->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'relevance' => $c->pivot->relevance_score,
            ]),
        ];

        $user = $request->user();
        if ($user) {
            $score = $this->matching->scoreJob($jobListing, $user);
            $data['match_score'] = $score;
            $data['match_percent'] = (int) round($score * 100);
            $data['is_saved'] = $user->savedJobs()->where('job_listing_id', $jobListing->id)->exists();
        }

        return response()->json(['data' => $data]);
    }

    public function recommended(Request $request): JsonResponse
    {
        $user = $request->user();
        $results = $this->matching->recommendedForUser($user, $request->query('limit', 20));

        $savedIds = $user->savedJobs()->pluck('job_listing_id')->toArray();

        $data = $results->map(function ($result) use ($savedIds) {
            $job = $result['job'];

            return [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'location' => $job->location,
                'is_remote' => $job->is_remote,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'source_url' => $job->source_url,
                'posted_at' => $job->posted_at?->toISOString(),
                'match_score' => $result['match_score'],
                'match_percent' => $result['match_percent'],
                'is_saved' => in_array($job->id, $savedIds),
                'categories' => $job->vocationalCategories->map(fn ($c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                ]),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function save(Request $request, JobListing $jobListing): JsonResponse
    {
        $request->user()->savedJobs()->syncWithoutDetaching([$jobListing->id]);

        return response()->json(['message' => 'Job saved.']);
    }

    public function unsave(Request $request, JobListing $jobListing): JsonResponse
    {
        $request->user()->savedJobs()->detach($jobListing->id);

        return response()->json(null, 204);
    }
}
