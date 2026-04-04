<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\VocationalProfile;
use App\Services\Analytics\OrgJobAnalyticsService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrgInsightsController extends Controller
{
    public function index(Organization $organization): Response
    {
        $assessmentIds = $organization->assessments()
            ->where('status', 'completed')
            ->pluck('id');

        // Primary domain distribution
        $domainDistribution = VocationalProfile::whereIn('assessment_id', $assessmentIds)
            ->whereNotNull('primary_domain')
            ->select('primary_domain', DB::raw('count(*) as count'))
            ->groupBy('primary_domain')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->primary_domain,
                'count' => $row->count,
            ]);

        $totalProfiles = $domainDistribution->sum('count');
        $domainDistribution = $domainDistribution->map(fn ($item) => [
            ...$item,
            'percentage' => $totalProfiles > 0
                ? round(($item['count'] / $totalProfiles) * 100)
                : 0,
        ]);

        // Mode of work distribution
        $modeDistribution = VocationalProfile::whereIn('assessment_id', $assessmentIds)
            ->whereNotNull('mode_of_work')
            ->select('mode_of_work', DB::raw('count(*) as count'))
            ->groupBy('mode_of_work')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->mode_of_work,
                'count' => $row->count,
                'percentage' => $totalProfiles > 0
                    ? round(($row->count / $totalProfiles) * 100)
                    : 0,
            ]);

        // Completion rates by month (last 6 months)
        $completionByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $started = $organization->assessments()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $completed = $organization->assessments()
                ->where('status', 'completed')
                ->whereYear('completed_at', $month->year)
                ->whereMonth('completed_at', $month->month)
                ->count();

            $completionByMonth[] = [
                'month' => $month->format('M Y'),
                'started' => $started,
                'completed' => $completed,
                'rate' => $started > 0 ? round(($completed / $started) * 100) : 0,
            ];
        }

        // Category scores (averaged and anonymized)
        $categoryScores = VocationalProfile::whereIn('assessment_id', $assessmentIds)
            ->whereNotNull('category_scores')
            ->pluck('category_scores');

        $aggregatedScores = [];
        foreach ($categoryScores as $scores) {
            if (! is_array($scores)) {
                continue;
            }
            foreach ($scores as $category => $score) {
                if (! isset($aggregatedScores[$category])) {
                    $aggregatedScores[$category] = ['total' => 0, 'count' => 0];
                }
                $aggregatedScores[$category]['total'] += (float) $score;
                $aggregatedScores[$category]['count']++;
            }
        }

        $avgScores = collect($aggregatedScores)
            ->map(fn ($data, $category) => [
                'category' => $category,
                'average' => round($data['total'] / $data['count'], 1),
                'respondents' => $data['count'],
            ])
            ->sortByDesc('average')
            ->values();

        $jobAnalytics = app(OrgJobAnalyticsService::class)->getJobAnalytics($organization);

        return Inertia::render('Org/Insights', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'domainDistribution' => $domainDistribution,
            'modeDistribution' => $modeDistribution,
            'completionByMonth' => $completionByMonth,
            'categoryScores' => $avgScores,
            'totalProfiles' => $totalProfiles,
            'jobAnalytics' => $jobAnalytics,
        ]);
    }
}
