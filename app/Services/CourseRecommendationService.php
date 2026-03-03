<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use App\Models\VocationalCategory;
use Illuminate\Support\Collection;

class CourseRecommendationService
{
    /**
     * Get recommended courses for a user based on their most recent completed assessment.
     *
     * @return Collection<int, array{course: Course, relevance: float, reason: string}>
     */
    public function forUser(User $user, ?Assessment $assessment = null): Collection
    {
        $assessment ??= $user->assessments()
            ->where('status', 'completed')
            ->with('vocationalProfile')
            ->latest('completed_at')
            ->first();

        if (! $assessment?->vocationalProfile) {
            return collect();
        }

        $profile = $assessment->vocationalProfile;
        $courses = Course::published()->with('vocationalCategory')->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $categoryMap = VocationalCategory::pluck('id', 'slug')->toArray();
        $categoryScores = collect($profile->category_scores ?? []);

        return $courses->map(function (Course $course) use ($profile, $categoryScores, $categoryMap) {
            $relevance = 0.0;
            $reason = '';

            $category = $course->vocationalCategory;
            if (! $category) {
                return null;
            }

            // Primary domain match (highest weight)
            if ($category->name === $profile->primary_domain) {
                $relevance += 50;
                $reason = "Directly aligned with your primary vocational domain";
            }

            // Secondary orientation match
            if ($category->name === $profile->secondary_orientation) {
                $relevance += 30;
                $reason = $reason ?: "Connects with your secondary vocational orientation";
            }

            // Category score match — higher scores in matching categories boost relevance
            $matchingScore = $categoryScores->first(fn ($score) => ($score['category'] ?? '') === $category->name);

            if ($matchingScore) {
                $scoreValue = $matchingScore['score'] ?? 0;
                $relevance += min($scoreValue * 0.2, 20); // Up to 20 points from category score
            }

            // Primary pathways overlap — check if course category has career pathways matching profile
            $profilePathways = collect($profile->primary_pathways ?? []);
            $coursePathways = collect($category->career_pathways ?? []);

            $overlap = $profilePathways->filter(fn ($pathway) => $coursePathways->contains(fn ($cp) => str_contains(strtolower($cp), strtolower($pathway)) || str_contains(strtolower($pathway), strtolower($cp))
            ));

            if ($overlap->isNotEmpty()) {
                $relevance += 10;
                $reason = $reason ?: "Covers career pathways in your vocational profile";
            }

            if ($relevance <= 0) {
                return null;
            }

            return [
                'course' => $course,
                'relevance' => round($relevance, 1),
                'reason' => $reason,
            ];
        })
            ->filter()
            ->sortByDesc('relevance')
            ->values();
    }
}
