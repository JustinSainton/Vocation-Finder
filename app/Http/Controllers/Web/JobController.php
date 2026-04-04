<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Models\VocationalCategory;
use App\Services\Jobs\JobMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(
        private JobMatchingService $matching,
    ) {}

    public function index(Request $request): Response
    {
        $query = JobListing::active()->classified()->with('vocationalCategories');

        if ($pathway = $request->query('pathway')) {
            $query->whereHas('vocationalCategories', fn ($q) => $q->where('slug', $pathway));
        }

        if ($request->has('remote')) {
            $query->where('is_remote', true);
        }

        if ($min = $request->query('salary_min')) {
            $query->where(fn ($q) => $q->whereNull('salary_max')->orWhere('salary_max', '>=', (int) $min));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('posted_at');

        $jobs = $query->paginate(20)->withQueryString();

        $user = $request->user();
        $savedIds = $user ? $user->savedJobs()->pluck('job_listing_id')->toArray() : [];

        $jobs->getCollection()->transform(function (JobListing $job) use ($user, $savedIds) {
            $data = [
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
                'is_saved' => in_array($job->id, $savedIds),
                'categories' => $job->vocationalCategories->map(fn ($c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                ]),
            ];

            if ($user) {
                $score = $this->matching->scoreJob($job, $user);
                $data['match_percent'] = (int) round($score * 100);
            }

            return $data;
        });

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => $request->only(['search', 'pathway', 'remote', 'salary_min']),
            'pathways' => VocationalCategory::orderBy('sort_order')->get(['slug', 'name']),
        ]);
    }

    public function show(Request $request, JobListing $jobListing): Response
    {
        $jobListing->load('vocationalCategories');

        $user = $request->user();
        $matchPercent = null;
        $isSaved = false;

        if ($user) {
            $score = $this->matching->scoreJob($jobListing, $user);
            $matchPercent = (int) round($score * 100);
            $isSaved = $user->savedJobs()->where('job_listing_id', $jobListing->id)->exists();
        }

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $jobListing->id,
                'title' => $jobListing->title,
                'company_name' => $jobListing->company_name,
                'company_url' => $jobListing->company_url,
                'location' => $jobListing->location,
                'is_remote' => $jobListing->is_remote,
                'salary_min' => $jobListing->salary_min,
                'salary_max' => $jobListing->salary_max,
                'salary_currency' => $jobListing->salary_currency,
                'description' => $jobListing->description_plain ?? strip_tags($jobListing->description ?? ''),
                'required_skills' => $jobListing->required_skills,
                'source_url' => $jobListing->source_url,
                'source' => $jobListing->source,
                'posted_at' => $jobListing->posted_at?->toISOString(),
                'match_percent' => $matchPercent,
                'is_saved' => $isSaved,
                'categories' => $jobListing->vocationalCategories->map(fn ($c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'relevance' => $c->pivot->relevance_score,
                ]),
            ],
        ]);
    }

    public function save(Request $request, JobListing $jobListing): JsonResponse
    {
        $request->user()->savedJobs()->syncWithoutDetaching([$jobListing->id]);

        return response()->json(['saved' => true]);
    }

    public function unsave(Request $request, JobListing $jobListing): JsonResponse
    {
        $request->user()->savedJobs()->detach($jobListing->id);

        return response()->json(['saved' => false]);
    }
}
