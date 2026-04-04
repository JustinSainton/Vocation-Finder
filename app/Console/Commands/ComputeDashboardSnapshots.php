<?php

namespace App\Console\Commands;

use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Console\Command;

class ComputeDashboardSnapshots extends Command
{
    protected $signature = 'dashboard:compute-snapshots';

    protected $description = 'Pre-compute platform analytics snapshots for fast dashboard loading';

    public function handle(PlatformAnalyticsService $analytics): int
    {
        $this->info('Computing platform KPIs...');
        $analytics->computeKpis();

        $this->info('Computing assessment volume...');
        $analytics->computeAssessmentVolume();

        $this->info('Computing survey analysis...');
        $analytics->computeSurveyAnalysis();

        $this->info('Computing job platform metrics...');
        $analytics->computeJobPlatformMetrics();

        $this->info('Dashboard snapshots computed successfully.');

        return self::SUCCESS;
    }
}
