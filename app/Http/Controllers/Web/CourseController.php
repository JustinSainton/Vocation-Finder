<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePersonalizedContentJob;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\PersonalizedContent;
use App\Models\VocationalCategory;
use App\Services\CourseRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = VocationalCategory::orderBy('sort_order')->get();

        $courses = Course::published()
            ->with('vocationalCategory')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'short_description' => $course->short_description,
                'estimated_duration' => $course->estimated_duration,
                'vocational_category_id' => $course->vocational_category_id,
                'category_name' => $course->vocationalCategory?->name,
                'requires_personalization' => $course->requires_personalization,
            ]);

        $grouped = $courses->groupBy('vocational_category_id');

        // Get personalized recommendations if user is authenticated and has a profile
        $recommendations = [];
        if ($request->user()) {
            $service = new CourseRecommendationService;
            $recommendations = $service->forUser($request->user())
                ->map(fn ($rec) => [
                    'course_id' => $rec['course']->id,
                    'course_slug' => $rec['course']->slug,
                    'course_title' => $rec['course']->title,
                    'relevance' => $rec['relevance'],
                    'reason' => $rec['reason'],
                ])
                ->take(5)
                ->values()
                ->toArray();
        }

        return Inertia::render('Courses/Index', [
            'categories' => $categories->map(fn (VocationalCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]),
            'coursesByCategory' => $grouped,
            'uncategorized' => $grouped->get('', collect())->merge($grouped->get(null, collect())),
            'recommendations' => $recommendations,
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $course->load(['modules', 'vocationalCategory']);

        $enrollment = null;
        $personalizationStatus = null;

        if ($request->user()) {
            $enrollment = CourseEnrollment::where('user_id', $request->user()->id)
                ->where('course_id', $course->id)
                ->first();

            // Check personalization status for this enrollment
            if ($enrollment && $course->requires_personalization && $enrollment->assessment_id) {
                $totalModules = $course->modules->count();
                $readyCount = PersonalizedContent::where('user_id', $request->user()->id)
                    ->where('assessment_id', $enrollment->assessment_id)
                    ->whereIn('course_module_id', $course->modules->pluck('id'))
                    ->where('status', 'ready')
                    ->count();

                $personalizationStatus = [
                    'total' => $totalModules,
                    'ready' => $readyCount,
                    'complete' => $readyCount >= $totalModules,
                ];
            }
        }

        return Inertia::render('Courses/Show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'short_description' => $course->short_description,
                'content_blocks' => $course->content_blocks,
                'estimated_duration' => $course->estimated_duration,
                'category_name' => $course->vocationalCategory?->name,
                'requires_personalization' => $course->requires_personalization,
                'modules' => $course->modules->map(fn (CourseModule $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'slug' => $m->slug,
                    'description' => $m->description,
                    'sort_order' => $m->sort_order,
                ]),
            ],
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'current_module_id' => $enrollment->current_module_id,
                'progress' => $enrollment->progress,
                'assessment_id' => $enrollment->assessment_id,
            ] : null,
            'personalizationStatus' => $personalizationStatus,
        ]);
    }

    public function module(Request $request, Course $course, CourseModule $module): Response
    {
        $course->load('modules');

        $modules = $course->modules;
        $currentIndex = $modules->search(fn ($m) => $m->id === $module->id);
        $nextModule = $currentIndex !== false ? $modules->get($currentIndex + 1) : null;
        $prevModule = $currentIndex !== false && $currentIndex > 0 ? $modules->get($currentIndex - 1) : null;

        // Resolve content blocks — personalized if available, template otherwise
        $contentBlocks = $module->content_blocks;
        $isPersonalized = false;

        if ($request->user() && $course->requires_personalization) {
            $enrollment = CourseEnrollment::where('user_id', $request->user()->id)
                ->where('course_id', $course->id)
                ->first();

            if ($enrollment?->assessment_id) {
                $personalized = PersonalizedContent::where('user_id', $request->user()->id)
                    ->where('course_module_id', $module->id)
                    ->where('assessment_id', $enrollment->assessment_id)
                    ->first();

                if ($personalized?->isReady()) {
                    $contentBlocks = $personalized->content_blocks;
                    $isPersonalized = true;
                }
            }
        }

        return Inertia::render('Courses/Module', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'slug' => $module->slug,
                'description' => $module->description,
                'content_blocks' => $contentBlocks,
                'sort_order' => $module->sort_order,
            ],
            'isPersonalized' => $isPersonalized,
            'nextModule' => $nextModule ? [
                'title' => $nextModule->title,
                'slug' => $nextModule->slug,
            ] : null,
            'prevModule' => $prevModule ? [
                'title' => $prevModule->title,
                'slug' => $prevModule->slug,
            ] : null,
        ]);
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        $existing = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $existing) {
            // Find the user's most recent completed assessment for personalization
            $assessment = $user->assessments()
                ->where('status', 'completed')
                ->whereHas('vocationalProfile')
                ->latest('completed_at')
                ->first();

            $enrollment = CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_slug' => $course->slug,
                'assessment_id' => $assessment?->id,
                'status' => 'enrolled',
            ]);

            // Dispatch personalization jobs for all modules if course requires it
            if ($course->requires_personalization && $assessment) {
                $this->dispatchPersonalizationJobs($user, $course, $assessment);
            }
        }

        $firstModule = $course->modules()->orderBy('sort_order')->first();

        if ($firstModule) {
            return redirect("/courses/{$course->slug}/modules/{$firstModule->slug}");
        }

        return redirect("/courses/{$course->slug}");
    }

    public function updateProgress(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'current_module_id' => 'required|uuid|exists:course_modules,id',
        ]);

        $enrollment = CourseEnrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $enrollment->update([
            'current_module_id' => $validated['current_module_id'],
        ]);

        return redirect()->back();
    }

    protected function dispatchPersonalizationJobs($user, Course $course, $assessment): void
    {
        $course->load('modules');

        foreach ($course->modules as $module) {
            $personalizedContent = PersonalizedContent::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_module_id' => $module->id,
                    'assessment_id' => $assessment->id,
                ],
                [
                    'status' => 'pending',
                ]
            );

            if ($personalizedContent->status === 'pending') {
                GeneratePersonalizedContentJob::dispatch($personalizedContent);
            }
        }
    }
}
