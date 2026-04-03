<?php

namespace App\Services\Analytics;

use App\Models\Assessment;
use App\Models\AssessmentSurvey;
use App\Models\DashboardSnapshot;
use App\Models\Organization;
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
            ->selectRaw('avg(after_s.clarity_score - before_s.clarity_score) as avg_clarity_delta')
            ->selectRaw('avg(after_s.action_score - before_s.action_score) as avg_action_delta')
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
}
