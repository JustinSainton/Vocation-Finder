<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\PlatformAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        private PlatformAnalyticsService $analytics,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'kpis' => $this->analytics->getKpis(),
            'assessmentVolume' => $this->analytics->getAssessmentVolume(),
            'surveyAnalysis' => $this->analytics->getSurveyAnalysis(),
            'domainDistribution' => $this->analytics->getDomainDistribution(),
            'recentAssessments' => $this->analytics->getRecentAssessments(),
            'orgUsage' => $this->analytics->getOrgUsage(),
            'jobPlatformMetrics' => $this->analytics->getJobPlatformMetrics(),
        ]);
    }
}
