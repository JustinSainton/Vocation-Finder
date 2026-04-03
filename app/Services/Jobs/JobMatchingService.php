<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use App\Models\User;
use App\Models\VocationalProfile;
use Illuminate\Support\Collection;

class JobMatchingService
{
    private const WEIGHT_VOCATIONAL = 0.5;

    private const WEIGHT_SKILLS = 0.3;

    private const WEIGHT_VALUES = 0.2;

    /**
     * Score a single job listing against a user's vocational profile.
     */
    public function scoreJob(JobListing $job, User $user): float
    {
        $profile = $this->getProfile($user);

        if (! $profile) {
            return 0.0;
        }

        $vocational = $this->vocationalAlignment($job, $profile);
        $skills = $this->skillsMatch($job, $user);
        $values = $this->valuesAlignment($job, $profile);

        return round(
            (self::WEIGHT_VOCATIONAL * $vocational)
            + (self::WEIGHT_SKILLS * $skills)
            + (self::WEIGHT_VALUES * $values),
            3
        );
    }

    /**
     * Get recommended jobs for a user, sorted by match score.
     */
    public function recommendedForUser(User $user, int $limit = 20): Collection
    {
        $profile = $this->getProfile($user);

        if (! $profile || empty($profile->category_scores)) {
            return collect();
        }

        // Get the user's top pathways and find jobs in those categories
        $topCategorySlugs = collect($profile->category_scores)
            ->sortDesc()
            ->take(5)
            ->keys();

        $jobs = JobListing::active()
            ->classified()
            ->whereHas('vocationalCategories', function ($q) use ($topCategorySlugs) {
                $q->whereIn('slug', $topCategorySlugs);
            })
            ->with('vocationalCategories')
            ->orderByDesc('posted_at')
            ->limit($limit * 3) // Over-fetch for scoring/reranking
            ->get();

        return $jobs->map(function (JobListing $job) use ($user, $profile) {
            $score = $this->scoreJob($job, $user);

            return [
                'job' => $job,
                'match_score' => $score,
                'match_percent' => (int) round($score * 100),
            ];
        })
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }

    /**
     * Cosine similarity between job's category weights and user's category_scores.
     */
    private function vocationalAlignment(JobListing $job, VocationalProfile $profile): float
    {
        $userScores = $profile->category_scores ?? [];

        if (empty($userScores)) {
            return 0.0;
        }

        $jobCategories = $job->vocationalCategories;

        if ($jobCategories->isEmpty()) {
            return 0.0;
        }

        // Build vectors over the same key space
        $allKeys = collect($userScores)->keys()
            ->merge($jobCategories->pluck('slug'))
            ->unique();

        $userVector = [];
        $jobVector = [];

        foreach ($allKeys as $key) {
            $userVector[] = (float) ($userScores[$key] ?? 0);
            $jobVector[] = (float) ($jobCategories->firstWhere('slug', $key)?->pivot->relevance_score ?? 0);
        }

        return $this->cosineSimilarity($userVector, $jobVector);
    }

    /**
     * Overlap between job's required_skills and user's career profile skills.
     */
    private function skillsMatch(JobListing $job, User $user): float
    {
        $jobSkills = collect($job->required_skills ?? [])
            ->map(fn ($s) => strtolower(is_string($s) ? $s : ($s['name'] ?? '')))
            ->filter();

        if ($jobSkills->isEmpty()) {
            return 0.5; // Neutral when job doesn't list skills
        }

        $careerProfile = $user->careerProfile;
        $userSkills = collect($careerProfile?->skills ?? [])
            ->map(fn ($s) => strtolower(is_string($s) ? $s : ($s['name'] ?? '')))
            ->filter();

        if ($userSkills->isEmpty()) {
            return 0.3; // Slight penalty for no user skills data
        }

        $overlap = $jobSkills->intersect($userSkills)->count();

        return min(1.0, $overlap / max(1, $jobSkills->count()));
    }

    /**
     * Values alignment based on primary domain and mode of work.
     */
    private function valuesAlignment(JobListing $job, VocationalProfile $profile): float
    {
        // Simple heuristic: does the job's top category match the user's primary domain?
        $topCategory = $job->vocationalCategories->sortByDesc('pivot.relevance_score')->first();

        if (! $topCategory || ! $profile->primary_domain) {
            return 0.5;
        }

        // Check if the job's top category aligns with user's primary domain
        $domainCategoryMap = [
            'People & Relationships' => ['healing-care', 'teaching-formation', 'advocating-supporting', 'pastoral-missionary'],
            'Ideas & Innovation' => ['discovering-innovating', 'communication-media', 'knowledge-information'],
            'Systems & Structures' => ['leadership-management', 'administration-systems', 'finance-economics', 'law-policy'],
            'Tangible & Physical' => ['creating-building', 'maintaining-repairing', 'arts-beauty', 'nourishing-hospitality'],
        ];

        $domainCategories = $domainCategoryMap[$profile->primary_domain] ?? [];

        if (in_array($topCategory->slug, $domainCategories)) {
            return 0.9;
        }

        // Check secondary orientation
        $secondaryCategories = $domainCategoryMap[$profile->secondary_orientation] ?? [];

        if (in_array($topCategory->slug, $secondaryCategories)) {
            return 0.7;
        }

        return 0.4;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] ** 2;
            $magnitudeB += $b[$i] ** 2;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    private function getProfile(User $user): ?VocationalProfile
    {
        $assessment = $user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();

        return $assessment?->vocationalProfile;
    }
}
