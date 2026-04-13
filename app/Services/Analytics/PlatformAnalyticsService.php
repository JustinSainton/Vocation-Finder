<?php

namespace App\Services\Analytics;

use App\Models\Assessment;
use App\Models\AssessmentSurvey;
use App\Models\CoverLetter;
use App\Models\DashboardSnapshot;
use App\Models\FeatureFlag;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\Organization;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Models\VocationalProfile;
use Illuminate\Support\Facades\DB;

class PlatformAnalyticsService
{
    /**
     * Get platform-wide KPI stats (from snapshot or live).
     */
    public function getKpis(): array
    {
        $snapshot = DashboardSnapshot::latest('platform_kpis');

        if ($snapshot && $snapshot->computed_at->isAfter(now()->subMinutes(15))) {
            return $snapshot->value;
        }

        return $this->computeKpis();
    }

    /**
     * Compute and store platform KPIs.
     */
    public function computeKpis(): array
    {
        $userStats = User::selectRaw('count(*) as total')
            ->selectRaw("sum(case when created_at >= ? then 1 else 0 end) as new_30d", [now()->subDays(30)])
            ->first();

        $assessmentStats = Assessment::selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            ->first();

        $activeOrgs = Organization::where('subscription_status', 'active')->count();

        $completionRate = $assessmentStats->total > 0
            ? round(($assessmentStats->completed / $assessmentStats->total) * 100, 1)
            : 0;

        $kpis = [
            'total_users' => $userStats->total,
            'new_users_30d' => $userStats->new_30d,
            'active_orgs' => $activeOrgs,
            'total_assessments' => $assessmentStats->total,
            'completed_assessments' => $assessmentStats->completed,
            'completion_rate' => $completionRate,
        ];

        DashboardSnapshot::record('platform_kpis', $kpis);

        return $kpis;
    }

    /**
     * Get assessment volume by day/week/month with automatic bucketing.
     */
    public function getAssessmentVolume(int $days = 90): array
    {
        $snapshot = DashboardSnapshot::latest('assessment_volume');

        if ($snapshot && $snapshot->computed_at->isAfter(now()->subMinutes(30))) {
            return $snapshot->value;
        }

        return $this->computeAssessmentVolume($days);
    }

    public function computeAssessmentVolume(int $days = 90): array
    {
        $driver = DB::getDriverName();
        $isSqlite = $driver === 'sqlite';

        // Use daily buckets for <90d, weekly for <365d, monthly for >365d
        if ($isSqlite) {
            $format = $days <= 90 ? '%Y-%m-%d' : ($days <= 365 ? '%Y-W%W' : '%Y-%m');
            $expr = "strftime('{$format}', created_at)";
        } else {
            $format = $days <= 90 ? '%Y-%m-%d' : ($days <= 365 ? '%Y-%u' : '%Y-%m');
            $expr = "DATE_FORMAT(created_at, '{$format}')";
        }

        $data = Assessment::selectRaw("{$expr} as period")
            ->selectRaw('count(*) as started')
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            ->where('created_at', '>=', now()->subDays($days))
            ->groupByRaw($expr)
            ->orderBy('period')
            ->get()
            ->toArray();

        DashboardSnapshot::record('assessment_volume', $data);

        return $data;
    }

    /**
     * Get survey score analysis (before/after deltas).
     */
    public function getSurveyAnalysis(): array
    {
        $snapshot = DashboardSnapshot::latest('survey_analysis');

        if ($snapshot && $snapshot->computed_at->isAfter(now()->subMinutes(30))) {
            return $snapshot->value;
        }

        return $this->computeSurveyAnalysis();
    }

    public function computeSurveyAnalysis(): array
    {
        // Average improvement (paired before/after)
        $deltas = DB::table('assessment_surveys as before_s')
            ->join('assessment_surveys as after_s', function ($join) {
                $join->on('before_s.assessment_id', '=', 'after_s.assessment_id')
                    ->where('before_s.type', '=', 'before')
                    ->where('after_s.type', '=', 'after');
            })
            ->selectRaw('avg(CAST(after_s.clarity_score AS SIGNED) - CAST(before_s.clarity_score AS SIGNED)) as avg_clarity_delta')
            ->selectRaw('avg(CAST(after_s.action_score AS SIGNED) - CAST(before_s.action_score AS SIGNED)) as avg_action_delta')
            ->selectRaw('count(*) as paired_count')
            ->first();

        // Clarity score distribution (before)
        $clarityBefore = AssessmentSurvey::where('type', 'before')
            ->selectRaw('clarity_score, count(*) as count')
            ->groupBy('clarity_score')
            ->orderBy('clarity_score')
            ->get()
            ->toArray();

        // Clarity score distribution (after)
        $clarityAfter = AssessmentSurvey::where('type', 'after')
            ->selectRaw('clarity_score, count(*) as count')
            ->groupBy('clarity_score')
            ->orderBy('clarity_score')
            ->get()
            ->toArray();

        $analysis = [
            'avg_clarity_delta' => round((float) ($deltas->avg_clarity_delta ?? 0), 2),
            'avg_action_delta' => round((float) ($deltas->avg_action_delta ?? 0), 2),
            'paired_count' => $deltas->paired_count ?? 0,
            'clarity_before' => $clarityBefore,
            'clarity_after' => $clarityAfter,
        ];

        DashboardSnapshot::record('survey_analysis', $analysis);

        return $analysis;
    }

    /**
     * Get top vocational domains distribution.
     */
    public function getDomainDistribution(): array
    {
        return VocationalProfile::selectRaw('primary_domain, count(*) as count')
            ->whereNotNull('primary_domain')
            ->groupBy('primary_domain')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get recent assessments for the admin table.
     */
    public function getRecentAssessments(int $limit = 10): array
    {
        return Assessment::with(['user:id,name,email', 'organization:id,name'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'user' => $a->user ? ['name' => $a->user->name, 'email' => $a->user->email] : null,
                'organization' => $a->organization?->name,
                'mode' => $a->mode,
                'status' => $a->status,
                'locale' => $a->locale,
                'created_at' => $a->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get org usage breakdown (assessments used vs limit).
     */
    public function getOrgUsage(): array
    {
        return Organization::where('subscription_status', 'active')
            ->withCount([
                'assessments as assessments_this_period' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()),
                'users as member_count',
            ])
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'member_count' => $org->member_count,
                'assessments_used' => $org->assessments_this_period,
                'assessments_limit' => $org->assessmentsPerPeriod(),
                'member_limit' => $org->memberLimit(),
            ])
            ->toArray();
    }

    /**
     * Get cost tracking data: expected vs estimated actual costs.
     */
    public function getCostTracking(): array
    {
        $snapshot = DashboardSnapshot::latest('cost_tracking');

        if ($snapshot && $snapshot->computed_at->isAfter(now()->subMinutes(30))) {
            return $snapshot->value;
        }

        return $this->computeCostTracking();
    }

    public function computeCostTracking(): array
    {
        // --- Cost-per-unit assumptions (from plan) ---
        $costPerClassification = 0.015;  // ~$0.01-0.02 avg per Haiku classification
        $costPerResume         = 0.30;   // per resume generation
        $costPerCoverLetter    = 0.25;   // per cover letter generation

        // --- Expected monthly baseline (from plan, MVP tier) ---
        $expectedCosts = [
            'adzuna'            => 0.00,
            'jsearch'           => 25.00,
            'muse'              => 0.00,
            'onet'              => 0.00,
            'ai_classification' => 15.00,  // ~$15 for 1k listings/day at MVP
        ];
        $expectedTotal = array_sum($expectedCosts);

        // --- Actual usage counts (current calendar month) ---
        $startOfMonth = now()->startOfMonth();

        $jobsIngestedThisMonth = JobListing::where('created_at', '>=', $startOfMonth)->count();

        $classificationsThisMonth = JobListing::where('classification_status', 'classified')
            ->where('updated_at', '>=', $startOfMonth)
            ->count();

        // Include failed classification attempts — they still cost an API call
        $classificationFailedThisMonth = JobListing::where('classification_status', 'failed')
            ->where('updated_at', '>=', $startOfMonth)
            ->count();

        $totalClassificationCalls = $classificationsThisMonth + $classificationFailedThisMonth;

        $resumesThisMonth = ResumeVersion::where('created_at', '>=', $startOfMonth)->count();

        $coverLettersThisMonth = CoverLetter::where('created_at', '>=', $startOfMonth)->count();

        // --- Estimated actual costs ---
        $actualAiClassificationCost = round($totalClassificationCalls * $costPerClassification, 2);
        $actualResumeCost           = round($resumesThisMonth * $costPerResume, 2);
        $actualCoverLetterCost      = round($coverLettersThisMonth * $costPerCoverLetter, 2);

        $actualCosts = [
            'ai_classification' => $actualAiClassificationCost,
            'resume_generation' => $actualResumeCost,
            'cover_letter_generation' => $actualCoverLetterCost,
        ];
        $actualTotal = round(array_sum($actualCosts), 2);

        // API subscription costs are fixed — include them as actual too
        $actualTotalWithSubscriptions = round(
            $actualTotal + $expectedCosts['jsearch'],
            2
        );

        $variance = round($actualTotalWithSubscriptions - $expectedTotal, 2);

        $costTracking = [
            'period' => now()->format('Y-m'),
            'expected' => [
                'line_items' => $expectedCosts,
                'total' => $expectedTotal,
            ],
            'actual' => [
                'usage' => [
                    'jobs_ingested' => $jobsIngestedThisMonth,
                    'ai_classifications' => $totalClassificationCalls,
                    'ai_classifications_succeeded' => $classificationsThisMonth,
                    'ai_classifications_failed' => $classificationFailedThisMonth,
                    'resumes_generated' => $resumesThisMonth,
                    'cover_letters_generated' => $coverLettersThisMonth,
                ],
                'unit_costs' => [
                    'classification' => $costPerClassification,
                    'resume' => $costPerResume,
                    'cover_letter' => $costPerCoverLetter,
                ],
                'line_items' => $actualCosts,
                'ai_subtotal' => $actualTotal,
                'subscription_costs' => $expectedCosts['jsearch'],
                'total' => $actualTotalWithSubscriptions,
            ],
            'variance' => $variance,
            'variance_pct' => $expectedTotal > 0
                ? round(($variance / $expectedTotal) * 100, 1)
                : 0,
        ];

        DashboardSnapshot::record('cost_tracking', $costTracking);

        return $costTracking;
    }

    /**
     * Get job platform metrics for admin dashboard.
     */
    public function getJobPlatformMetrics(): array
    {
        $snapshot = DashboardSnapshot::latest('job_platform_metrics');

        if ($snapshot && $snapshot->computed_at->isAfter(now()->subMinutes(30))) {
            return $snapshot->value;
        }

        return $this->computeJobPlatformMetrics();
    }

    public function computeJobPlatformMetrics(): array
    {
        // Job ingestion stats
        $totalJobs = JobListing::count();
        $activeJobs = JobListing::active()->count();
        $classifiedJobs = JobListing::classified()->count();

        $jobsBySource = JobListing::select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        $jobsLast7d = JobListing::where('created_at', '>=', now()->subDays(7))->count();

        // Resume generation stats
        $resumeTotal = ResumeVersion::count();
        $resumeReady = ResumeVersion::where('status', 'ready')->count();
        $avgQuality = ResumeVersion::where('status', 'ready')->avg('quality_score');
        $coverLetterTotal = CoverLetter::count();

        // Application funnel (platform-wide)
        $allApps = JobApplication::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalApplied = ($allApps['applied'] ?? 0) + ($allApps['interviewing'] ?? 0) + ($allApps['offered'] ?? 0) + ($allApps['accepted'] ?? 0);
        $totalInterviewing = ($allApps['interviewing'] ?? 0) + ($allApps['offered'] ?? 0) + ($allApps['accepted'] ?? 0);

        // Feature flag status
        $flags = FeatureFlag::orderBy('key')->get(['key', 'name', 'is_enabled'])->toArray();

        // Active users on job features (last 30 days)
        $activeJobUsers = JobApplication::where('updated_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        $metrics = [
            'jobs' => [
                'total' => $totalJobs,
                'active' => $activeJobs,
                'classified' => $classifiedJobs,
                'by_source' => $jobsBySource,
                'ingested_7d' => $jobsLast7d,
            ],
            'resumes' => [
                'total_generated' => $resumeTotal,
                'ready' => $resumeReady,
                'avg_quality' => $avgQuality ? round($avgQuality, 1) : null,
            ],
            'cover_letters' => [
                'total_generated' => $coverLetterTotal,
            ],
            'application_funnel' => [
                'total' => array_sum($allApps),
                'applied' => $totalApplied,
                'interviewing' => $totalInterviewing,
                'offered' => ($allApps['offered'] ?? 0) + ($allApps['accepted'] ?? 0),
                'accepted' => $allApps['accepted'] ?? 0,
                'ghosted' => $allApps['ghosted'] ?? 0,
            ],
            'active_job_users_30d' => $activeJobUsers,
            'feature_flags' => $flags,
        ];

        DashboardSnapshot::record('job_platform_metrics', $metrics);

        return $metrics;
    }
}
