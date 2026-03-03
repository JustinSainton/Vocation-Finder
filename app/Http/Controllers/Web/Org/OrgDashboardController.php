<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

class OrgDashboardController extends Controller
{
    public function index(Organization $organization): Response
    {
        $memberCount = $organization->users()->count();
        $memberLimit = $organization->memberLimit();
        $assessmentsThisPeriod = $organization->assessmentsUsedThisPeriod();
        $assessmentsLimit = $organization->assessmentsPerPeriod();

        $totalAssessments = $organization->assessments()->count();
        $completedAssessments = $organization->assessments()
            ->where('status', 'completed')
            ->count();
        $completionRate = $totalAssessments > 0
            ? round(($completedAssessments / $totalAssessments) * 100)
            : 0;

        $recentActivity = $organization->assessments()
            ->with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'user_name' => $a->user?->name ?? 'Guest',
                'user_email' => $a->user?->email,
                'status' => $a->status,
                'mode' => $a->mode,
                'created_at' => $a->created_at->toDateString(),
                'completed_at' => $a->completed_at?->toDateString(),
            ]);

        return Inertia::render('Org/Dashboard', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'subscription_status' => $organization->subscription_status,
            ],
            'stats' => [
                'member_count' => $memberCount,
                'member_limit' => $memberLimit,
                'assessments_this_period' => $assessmentsThisPeriod,
                'assessments_limit' => $assessmentsLimit,
                'total_assessments' => $totalAssessments,
                'completed_assessments' => $completedAssessments,
                'completion_rate' => $completionRate,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }
}
