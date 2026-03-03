<?php

namespace App\Services;

use App\Ai\Agents\CurriculumCuration;
use App\Jobs\GeneratePersonalizedContentJob;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CurriculumPathway;
use App\Models\CurriculumPathwayCourse;
use App\Models\PersonalizedContent;
use App\Models\User;
use App\Models\VocationalCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurriculumEngine
{
    public function generate(User $user, Assessment $assessment): CurriculumPathway
    {
        $profile = $assessment->vocationalProfile;
        if (! $profile) {
            throw new \RuntimeException('Assessment has no vocational profile — cannot generate curriculum.');
        }

        $profileContext = $this->buildProfileContext($user, $profile);
        $courseCatalog = $this->buildCourseCatalog();

        if (empty($courseCatalog)) {
            throw new \RuntimeException('No published courses available for curriculum generation.');
        }

        $agent = new CurriculumCuration($profileContext, $courseCatalog);
        $response = $agent->prompt(
            $agent->buildPrompt(),
            model: config('vocation.ai.model', 'claude-sonnet-4-20250514'),
        );

        $result = $response->structured;
        $this->validateResult($result, $courseCatalog);

        return $this->persistPathway($user, $assessment, $result);
    }

    protected function buildProfileContext(User $user, $profile): array
    {
        return [
            'name' => $user->name,
            'primary_domain' => $profile->primary_domain,
            'mode_of_work' => $profile->mode_of_work,
            'secondary_orientation' => $profile->secondary_orientation,
            'primary_pathways' => $profile->primary_pathways,
            'category_scores' => $profile->category_scores,
            'opening_synthesis' => $profile->opening_synthesis,
            'vocational_orientation' => $profile->vocational_orientation,
            'ministry_integration' => $profile->ministry_integration,
        ];
    }

    protected function buildCourseCatalog(): array
    {
        return Course::published()
            ->with(['vocationalCategory', 'vocationalCategories'])
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->short_description ?? $course->description,
                'difficulty_level' => $course->difficulty_level,
                'phase_tag' => $course->phase_tag,
                'primary_category' => $course->vocationalCategory?->name,
                'categories' => $course->vocationalCategories->map(fn ($cat) => [
                    'name' => $cat->name,
                    'relevance_weight' => $cat->pivot->relevance_weight,
                ])->toArray(),
                'requires_personalization' => $course->requires_personalization,
                'estimated_duration' => $course->estimated_duration,
            ])
            ->toArray();
    }

    protected function validateResult(array $result, array $catalog): void
    {
        $validIds = collect($catalog)->pluck('id')->toArray();
        $selectedIds = [];

        foreach (['discovery', 'deepening', 'integration', 'application'] as $phase) {
            $courses = $result['phases'][$phase]['courses'] ?? [];
            foreach ($courses as $course) {
                $courseId = $course['course_id'];

                if (! in_array($courseId, $validIds)) {
                    Log::warning('CurriculumEngine: AI selected invalid course ID', [
                        'course_id' => $courseId,
                        'phase' => $phase,
                    ]);
                    throw new \RuntimeException("AI selected invalid course ID: {$courseId}");
                }

                if (in_array($courseId, $selectedIds)) {
                    Log::warning('CurriculumEngine: AI selected duplicate course', [
                        'course_id' => $courseId,
                        'phase' => $phase,
                    ]);
                    throw new \RuntimeException("AI selected duplicate course: {$courseId}");
                }

                $selectedIds[] = $courseId;
            }
        }

        if (count($selectedIds) < 4) {
            throw new \RuntimeException('AI selected fewer than 4 courses — curriculum is too thin.');
        }
    }

    protected function persistPathway(User $user, Assessment $assessment, array $result): CurriculumPathway
    {
        $pendingPersonalization = [];

        $pathway = DB::transaction(function () use ($user, $assessment, $result, &$pendingPersonalization) {
            $pathway = CurriculumPathway::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'assessment_id' => $assessment->id,
                ],
                [
                    'status' => 'ready',
                    'phases' => $result['phases'],
                    'ai_rationale' => $result,
                    'pathway_summary' => $result['pathway_summary'],
                    'generated_at' => now(),
                ],
            );

            // Clear any previous pathway courses if regenerating
            $pathway->pathwayCourses()->delete();

            foreach (['discovery', 'deepening', 'integration', 'application'] as $phase) {
                $courses = $result['phases'][$phase]['courses'] ?? [];
                foreach ($courses as $position => $courseData) {
                    $course = Course::find($courseData['course_id']);
                    if (! $course) {
                        continue;
                    }

                    // Auto-enroll user in the course
                    $enrollment = CourseEnrollment::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'course_id' => $course->id,
                        ],
                        [
                            'course_slug' => $course->slug,
                            'assessment_id' => $assessment->id,
                            'status' => 'enrolled',
                        ],
                    );

                    CurriculumPathwayCourse::create([
                        'curriculum_pathway_id' => $pathway->id,
                        'course_id' => $course->id,
                        'phase' => $phase,
                        'position_in_phase' => $position,
                        'selection_rationale' => $courseData['rationale'] ?? null,
                        'enrollment_id' => $enrollment->id,
                    ]);

                    // Queue personalization for courses that require it
                    if ($course->requires_personalization) {
                        $modules = $course->modules()->get();
                        foreach ($modules as $module) {
                            $pc = PersonalizedContent::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'course_module_id' => $module->id,
                                    'assessment_id' => $assessment->id,
                                ],
                                [
                                    'status' => 'pending',
                                ],
                            );

                            if ($pc->status === 'pending') {
                                $pendingPersonalization[] = $pc;
                            }
                        }
                    }
                }
            }

            return $pathway;
        });

        // Dispatch personalization jobs after transaction commits
        foreach ($pendingPersonalization as $pc) {
            GeneratePersonalizedContentJob::dispatch($pc);
        }

        return $pathway;
    }
}
