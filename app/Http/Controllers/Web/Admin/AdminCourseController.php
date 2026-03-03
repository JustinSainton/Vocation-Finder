<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\VocationalCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCourseController extends Controller
{
    public function index(): Response
    {
        $courses = Course::with('vocationalCategory')
            ->withCount(['modules', 'enrollments'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'is_published' => $course->is_published,
                'published_at' => $course->published_at?->toDateString(),
                'category_name' => $course->vocationalCategory?->name,
                'modules_count' => $course->modules_count,
                'enrollments_count' => $course->enrollments_count,
                'estimated_duration' => $course->estimated_duration,
                'sort_order' => $course->sort_order,
            ]);

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function create(): Response
    {
        $categories = VocationalCategory::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Courses/Form', [
            'categories' => $categories,
            'course' => null,
            'allCourses' => Course::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'content_blocks' => 'nullable|json',
            'vocational_category_id' => 'nullable|uuid|exists:vocational_categories,id',
            'estimated_duration' => 'nullable|string|max:100',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'difficulty_level' => 'nullable|string|in:foundational,intermediate,advanced',
            'phase_tag' => 'nullable|string|in:discovery,deepening,integration,application',
            'prerequisite_course_ids' => 'nullable|array',
            'prerequisite_course_ids.*' => 'uuid|exists:courses,id',
            'category_tags' => 'nullable|array',
            'category_tags.*.id' => 'required|uuid|exists:vocational_categories,id',
            'category_tags.*.weight' => 'required|numeric|min:0|max:1',
            'modules' => 'nullable|array',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.content_blocks' => 'nullable|json',
            'modules.*.sort_order' => 'integer',
        ]);

        $course = Course::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'description' => $validated['description'],
            'short_description' => $validated['short_description'] ?? null,
            'content_blocks' => $validated['content_blocks'] ? json_decode($validated['content_blocks'], true) : null,
            'vocational_category_id' => $validated['vocational_category_id'] ?? null,
            'estimated_duration' => $validated['estimated_duration'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
            'difficulty_level' => $validated['difficulty_level'] ?? 'foundational',
            'phase_tag' => $validated['phase_tag'] ?? 'discovery',
            'prerequisite_course_ids' => $validated['prerequisite_course_ids'] ?? null,
        ]);

        // Sync multi-category tags
        if (!empty($validated['category_tags'])) {
            $syncData = [];
            foreach ($validated['category_tags'] as $tag) {
                $syncData[$tag['id']] = ['relevance_weight' => $tag['weight']];
            }
            $course->vocationalCategories()->sync($syncData);
        }

        if (!empty($validated['modules'])) {
            foreach ($validated['modules'] as $moduleData) {
                CourseModule::create([
                    'course_id' => $course->id,
                    'title' => $moduleData['title'],
                    'slug' => Str::slug($moduleData['title']),
                    'description' => $moduleData['description'] ?? null,
                    'content_blocks' => isset($moduleData['content_blocks'])
                        ? json_decode($moduleData['content_blocks'], true)
                        : null,
                    'sort_order' => $moduleData['sort_order'] ?? 0,
                ]);
            }
        }

        return redirect('/admin/courses');
    }

    public function edit(Course $course): Response
    {
        $course->load(['modules', 'vocationalCategories']);
        $categories = VocationalCategory::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Courses/Form', [
            'categories' => $categories,
            'allCourses' => Course::where('id', '!=', $course->id)->orderBy('title')->get(['id', 'title']),
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'short_description' => $course->short_description,
                'content_blocks' => $course->content_blocks,
                'vocational_category_id' => $course->vocational_category_id,
                'estimated_duration' => $course->estimated_duration,
                'sort_order' => $course->sort_order,
                'is_published' => $course->is_published,
                'difficulty_level' => $course->difficulty_level,
                'phase_tag' => $course->phase_tag,
                'prerequisite_course_ids' => $course->prerequisite_course_ids,
                'category_tags' => $course->vocationalCategories->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'weight' => (float) $cat->pivot->relevance_weight,
                ]),
                'modules' => $course->modules->map(fn (CourseModule $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'slug' => $m->slug,
                    'description' => $m->description,
                    'content_blocks' => $m->content_blocks,
                    'sort_order' => $m->sort_order,
                ]),
            ],
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'content_blocks' => 'nullable|json',
            'vocational_category_id' => 'nullable|uuid|exists:vocational_categories,id',
            'estimated_duration' => 'nullable|string|max:100',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'difficulty_level' => 'nullable|string|in:foundational,intermediate,advanced',
            'phase_tag' => 'nullable|string|in:discovery,deepening,integration,application',
            'prerequisite_course_ids' => 'nullable|array',
            'prerequisite_course_ids.*' => 'uuid|exists:courses,id',
            'category_tags' => 'nullable|array',
            'category_tags.*.id' => 'required|uuid|exists:vocational_categories,id',
            'category_tags.*.weight' => 'required|numeric|min:0|max:1',
            'modules' => 'nullable|array',
            'modules.*.id' => 'nullable|uuid',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.content_blocks' => 'nullable|json',
            'modules.*.sort_order' => 'integer',
        ]);

        $wasPublished = $course->is_published;
        $isNowPublished = $validated['is_published'] ?? false;

        $course->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'description' => $validated['description'],
            'short_description' => $validated['short_description'] ?? null,
            'content_blocks' => $validated['content_blocks'] ? json_decode($validated['content_blocks'], true) : null,
            'vocational_category_id' => $validated['vocational_category_id'] ?? null,
            'estimated_duration' => $validated['estimated_duration'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_published' => $isNowPublished,
            'published_at' => (!$wasPublished && $isNowPublished) ? now() : $course->published_at,
            'difficulty_level' => $validated['difficulty_level'] ?? $course->difficulty_level,
            'phase_tag' => $validated['phase_tag'] ?? $course->phase_tag,
            'prerequisite_course_ids' => $validated['prerequisite_course_ids'] ?? null,
        ]);

        // Sync multi-category tags
        $syncData = [];
        foreach ($validated['category_tags'] ?? [] as $tag) {
            $syncData[$tag['id']] = ['relevance_weight' => $tag['weight']];
        }
        $course->vocationalCategories()->sync($syncData);

        if (isset($validated['modules'])) {
            $existingIds = collect($validated['modules'])->pluck('id')->filter()->all();
            $course->modules()->whereNotIn('id', $existingIds)->delete();

            foreach ($validated['modules'] as $moduleData) {
                if (!empty($moduleData['id'])) {
                    CourseModule::where('id', $moduleData['id'])->update([
                        'title' => $moduleData['title'],
                        'slug' => Str::slug($moduleData['title']),
                        'description' => $moduleData['description'] ?? null,
                        'content_blocks' => isset($moduleData['content_blocks'])
                            ? json_decode($moduleData['content_blocks'], true)
                            : null,
                        'sort_order' => $moduleData['sort_order'] ?? 0,
                    ]);
                } else {
                    CourseModule::create([
                        'course_id' => $course->id,
                        'title' => $moduleData['title'],
                        'slug' => Str::slug($moduleData['title']),
                        'description' => $moduleData['description'] ?? null,
                        'content_blocks' => isset($moduleData['content_blocks'])
                            ? json_decode($moduleData['content_blocks'], true)
                            : null,
                        'sort_order' => $moduleData['sort_order'] ?? 0,
                    ]);
                }
            }
        }

        return redirect('/admin/courses');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect('/admin/courses');
    }
}
