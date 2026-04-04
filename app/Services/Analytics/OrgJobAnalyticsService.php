<?php

namespace App\Services\Analytics;

use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\ResumeVersion;
use Illuminate\Support\Facades\DB;

class OrgJobAnalyticsService
{
    public function getJobAnalytics(Organization $organization): array
    {
        $memberIds = $organization->users()->pluck('users.id');

        if ($memberIds->isEmpty()) {
            return $this->emptyAnalytics();
        }

        $applications = JobApplication::whereIn('user_id', $memberIds)->get();

        // Application volume
        $totalApps = $applications->count();
        $appliedCount = $applications->whereNotNull('applied_at')->count();
        $interviewingCount = $applications->whereIn('status', ['interviewing', 'offered', 'accepted'])->count();
        $offeredCount = $applications->whereIn('status', ['offered', 'accepted', 'declined'])->count();
        $acceptedCount = $applications->where('status', 'accepted')->count();

        // Placement rate (members who got accepted)
        $membersWithAccepted = $applications->where('status', 'accepted')->pluck('user_id')->unique()->count();
        $totalActiveMembers = $memberIds->count();
        $placementRate = $totalActiveMembers > 0 ? round($membersWithAccepted / $totalActiveMembers, 2) : 0;

        // Avg time to placement
        $acceptedApps = $applications->where('status', 'accepted')->filter(fn ($a) => $a->applied_at);
        $avgDaysToPlacement = $acceptedApps->isNotEmpty()
            ? round($acceptedApps->avg(fn ($a) => $a->applied_at->diffInDays($a->updated_at)), 1)
            : null;

        // Top pathways pursued
        $pathwayStats = JobApplication::whereIn('job_applications.user_id', $memberIds)
            ->join('job_listings', 'job_applications.job_listing_id', '=', 'job_listings.id')
            ->join('job_listing_categories', 'job_listings.id', '=', 'job_listing_categories.job_listing_id')
            ->join('vocational_categories', 'job_listing_categories.vocational_category_id', '=', 'vocational_categories.id')
            ->select('vocational_categories.name', DB::raw('count(*) as count'))
            ->groupBy('vocational_categories.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => $row->count]);

        // Resume generation usage
        $resumeStats = ResumeVersion::whereIn('user_id', $memberIds)
            ->selectRaw('count(*) as total')
            ->selectRaw("avg(quality_score) as avg_quality")
            ->first();

        // Active job seekers (members who searched/saved/applied in last 30 days)
        $activeJobSeekers = JobApplication::whereIn('user_id', $memberIds)
            ->where('updated_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        // Application trend (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = $applications
                ->filter(fn ($a) => $a->created_at->year === $month->year && $a->created_at->month === $month->month)
                ->count();
            $trend[] = ['month' => $month->format('M Y'), 'count' => $count];
        }

        return [
            'active_job_seekers' => $activeJobSeekers,
            'total_applications' => $totalApps,
            'funnel' => [
                'applied' => $appliedCount,
                'interviewing' => $interviewingCount,
                'offered' => $offeredCount,
                'accepted' => $acceptedCount,
            ],
            'interview_rate' => $appliedCount > 0 ? round($interviewingCount / $appliedCount, 2) : 0,
            'placement_rate' => $placementRate,
            'members_placed' => $membersWithAccepted,
            'avg_days_to_placement' => $avgDaysToPlacement,
            'top_pathways' => $pathwayStats,
            'resume_stats' => [
                'total_generated' => $resumeStats->total ?? 0,
                'avg_quality' => $resumeStats->avg_quality ? round($resumeStats->avg_quality, 1) : null,
            ],
            'application_trend' => $trend,
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'active_job_seekers' => 0,
            'total_applications' => 0,
            'funnel' => ['applied' => 0, 'interviewing' => 0, 'offered' => 0, 'accepted' => 0],
            'interview_rate' => 0,
            'placement_rate' => 0,
            'members_placed' => 0,
            'avg_days_to_placement' => null,
            'top_pathways' => [],
            'resume_stats' => ['total_generated' => 0, 'avg_quality' => null],
            'application_trend' => [],
        ];
    }
}
