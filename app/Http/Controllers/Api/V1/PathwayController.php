<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CurriculumPathway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PathwayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pathway = $request->user()
            ->curriculumPathways()
            ->with(['pathwayCourses.course.vocationalCategory', 'pathwayCourses.enrollment'])
            ->latest()
            ->first();

        if (! $pathway) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->formatPathway($pathway)]);
    }

    public function show(Request $request, CurriculumPathway $pathway): JsonResponse
    {
        abort_unless($pathway->user_id === $request->user()->id, 403);

        $pathway->load(['pathwayCourses.course.vocationalCategory', 'pathwayCourses.enrollment']);

        return response()->json(['data' => $this->formatPathway($pathway)]);
    }

    protected function formatPathway(CurriculumPathway $pathway): array
    {
        $phases = [];
        foreach (['discovery', 'deepening', 'integration', 'application'] as $phase) {
            $phaseCourses = $pathway->pathwayCourses
                ->where('phase', $phase)
                ->sortBy('position_in_phase')
                ->values();

            $phases[$phase] = [
                'description' => $pathway->phases[$phase]['description'] ?? '',
                'courses' => $phaseCourses->map(fn ($pc) => [
                    'id' => $pc->course->id,
                    'title' => $pc->course->title,
                    'slug' => $pc->course->slug,
                    'short_description' => $pc->course->short_description,
                    'estimated_duration' => $pc->course->estimated_duration,
                    'category_name' => $pc->course->vocationalCategory?->name,
                    'difficulty_level' => $pc->course->difficulty_level,
                    'rationale' => $pc->selection_rationale,
                    'enrollment_status' => $pc->enrollment?->status,
                ])->toArray(),
            ];
        }

        $totalCourses = $pathway->pathwayCourses->count();
        $completedCourses = $pathway->pathwayCourses
            ->filter(fn ($pc) => $pc->enrollment?->status === 'completed')
            ->count();

        return [
            'id' => $pathway->id,
            'status' => $pathway->status,
            'pathway_summary' => $pathway->pathway_summary,
            'generated_at' => $pathway->generated_at?->toISOString(),
            'phases' => $phases,
            'progress' => [
                'total' => $totalCourses,
                'completed' => $completedCourses,
            ],
        ];
    }
}
