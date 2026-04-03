<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Http\JsonResponse;

class AdminStatsController extends Controller
{
    public function __construct(
        private PlatformAnalyticsService $analytics,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'kpis' => $this->analytics->getKpis(),
            'assessment_volume' => $this->analytics->getAssessmentVolume(),
            'survey_analysis' => $this->analytics->getSurveyAnalysis(),
            'domain_distribution' => $this->analytics->getDomainDistribution(),
        ]);
    }
}
