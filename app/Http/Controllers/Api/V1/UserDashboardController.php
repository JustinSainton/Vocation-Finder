<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MentorNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    /**
     * Aggregated dashboard data for the mobile app.
     * Returns profile summary, latest assessment, pathway status, and shared mentor notes in one call.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Latest completed assessment with vocational profile
        $latestAssessment = $user->assessments()
            ->where('status', 'completed')
            ->with('vocationalProfile:id,assessment_id,primary_domain,mode_of_work,primary_pathways,opening_synthesis')
            ->latest('completed_at')
            ->first();

        $profileSummary = null;
        if ($latestAssessment?->vocationalProfile) {
            $vp = $latestAssessment->vocationalProfile;
            $profileSummary = [
                'primary_domain' => $vp->primary_domain,
                'mode_of_work' => $vp->mode_of_work,
                'primary_pathways' => $vp->primary_pathways,
                'opening_synthesis' => $vp->opening_synthesis,
                'assessment_id' => $latestAssessment->id,
                'completed_at' => $latestAssessment->completed_at?->toISOString(),
            ];
        }

        // Current in-progress assessment
        $inProgressAssessment = $user->assessments()
            ->whereIn('status', ['in_progress', 'analyzing'])
            ->latest()
            ->first(['id', 'mode', 'status', 'created_at']);

        // Curriculum pathway status
        $pathway = $user->latestCurriculumPathway;
        $pathway?->load('pathwayCourses.course:id,title,short_description');

        $pathwaySummary = null;
        if ($pathway) {
            $pathwaySummary = [
                'id' => $pathway->id,
                'status' => $pathway->status,
                'summary' => $pathway->pathway_summary,
                'phases' => $pathway->phases,
                'total_courses' => $pathway->pathwayCourses->count(),
            ];
        }

        // Organization memberships
        $organizations = $user->organizations()
            ->withPivot('role')
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'role' => $org->pivot->role,
            ]);

        // Shared mentor notes (across all orgs)
        $mentorNotes = MentorNote::where('student_id', $user->id)
            ->where('visibility', 'shared')
            ->with('mentor:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($note) => [
                'id' => $note->id,
                'content' => $note->content,
                'mentor_name' => $note->mentor?->name,
                'created_at' => $note->created_at->toISOString(),
            ]);

        // Assessment stats
        $assessmentCount = $user->assessments()->count();
        $completedCount = $user->assessments()->where('status', 'completed')->count();

        return response()->json([
            'profile_summary' => $profileSummary,
            'in_progress_assessment' => $inProgressAssessment,
            'pathway' => $pathwaySummary,
            'organizations' => $organizations,
            'mentor_notes' => $mentorNotes,
            'stats' => [
                'total_assessments' => $assessmentCount,
                'completed_assessments' => $completedCount,
            ],
        ]);
    }

    /**
     * Get shared mentor notes for the authenticated user.
     */
    public function mentorNotes(Request $request): JsonResponse
    {
        $notes = MentorNote::where('student_id', $request->user()->id)
            ->where('visibility', 'shared')
            ->with(['mentor:id,name', 'organization:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json($notes);
    }
}
