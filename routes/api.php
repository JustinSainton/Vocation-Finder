<?php

use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\CareerProfileController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\JobListingController;
use App\Http\Controllers\Api\V1\AssessmentSurveyController;
use App\Http\Controllers\Api\V1\AudioConversationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\CareerCoachController;
use App\Http\Controllers\Api\V1\CoverLetterController;
use App\Http\Controllers\Api\V1\JobApplicationController;
use App\Http\Controllers\Api\V1\PathwayController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\ResumeController;
use App\Http\Controllers\Api\V1\ResumeConversationController;
use App\Http\Controllers\Api\V1\ResultsController;
use App\Http\Controllers\Api\V1\VoiceProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authenticated user
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API v1
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/social/google', [AuthController::class, 'socialGoogle']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    // Feature flags (public, cached)
    Route::get('features', [FeatureFlagController::class, 'index']);

    // Public — no auth required
    Route::get('questions', [QuestionController::class, 'index']);

    // Assessment — works for both authenticated and guest users
    Route::post('assessments', [AssessmentController::class, 'store']);
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);
    Route::post('assessments/{assessment}/answers', [AssessmentController::class, 'saveAnswer']);
    Route::patch('assessments/{assessment}/answers/{answer}', [AssessmentController::class, 'updateAnswer']);
    Route::post('assessments/{assessment}/complete', [AssessmentController::class, 'complete']);
    Route::post('assessments/{assessment}/surveys', [AssessmentSurveyController::class, 'store']);

    // Results
    Route::get('assessments/{assessment}/results', [ResultsController::class, 'show']);
    Route::get('assessments/{assessment}/results/pdf', [ResultsController::class, 'pdf']);
    Route::post('assessments/{assessment}/results/email', [ResultsController::class, 'email']);

    // Audio conversation
    Route::post('conversations/start', [AudioConversationController::class, 'start']);
    Route::post('conversations/{session}/audio', [AudioConversationController::class, 'uploadAudio']);
    Route::post('conversations/{session}/turn', [AudioConversationController::class, 'processTurn']);
    Route::post('conversations/{session}/complete', [AudioConversationController::class, 'complete']);
    Route::post('conversations/speech', [AudioConversationController::class, 'synthesizeSpeech']);
    Route::get('conversations/speech-file', [AudioConversationController::class, 'streamSpeech'])
        ->name('api.v1.conversations.speech-file');

    // Courses (public)
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{course}', [CourseController::class, 'show']);
    Route::get('courses/{course}/modules/{module}', [CourseController::class, 'module']);

    // Invitations (public show)
    Route::get('invitations/{token}', [OrganizationInvitationController::class, 'show']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('assessments', function (Request $request) {
            return $request->user()->assessments()
                ->with('vocationalProfile')
                ->latest()
                ->paginate(10);
        });

        // Billing
        Route::get('billing/access-check', [BillingController::class, 'accessCheck']);
        Route::get('billing/usage', [BillingController::class, 'billingUsage']);
        Route::post('billing/checkout/individual', [BillingController::class, 'checkoutIndividual']);
        Route::post('billing/checkout/organization', [BillingController::class, 'checkoutOrganization']);
        Route::get('billing/portal', [BillingController::class, 'billingPortal']);

        // Invitations
        Route::post('invitations', [OrganizationInvitationController::class, 'store']);
        Route::post('invitations/{token}/accept', [OrganizationInvitationController::class, 'accept']);

        // Courses (authenticated)
        Route::get('courses/recommendations', [CourseController::class, 'recommendations']);
        Route::post('courses/{course}/enroll', [CourseController::class, 'enroll']);
        Route::patch('courses/{course}/progress', [CourseController::class, 'progress']);

        // Learning pathway
        Route::get('pathway', [PathwayController::class, 'index']);
        Route::get('pathway/{pathway}', [PathwayController::class, 'show']);

        // User dashboard (aggregated)
        Route::get('me/dashboard', [\App\Http\Controllers\Api\V1\UserDashboardController::class, 'index']);
        Route::get('me/mentor-notes', [\App\Http\Controllers\Api\V1\UserDashboardController::class, 'mentorNotes']);

        // Push notification token
        Route::post('push-token', function (Request $request) {
            $request->validate(['token' => 'required|string', 'platform' => 'nullable|string']);
            $request->user()->update(['expo_push_token' => $request->token]);
            return response()->json(['saved' => true]);
        });

        // Job Discovery (gated behind feature flag)
        Route::middleware('feature:job_discovery')->group(function () {
            Route::get('jobs', [JobListingController::class, 'index']);
            Route::get('jobs/recommended', [JobListingController::class, 'recommended']);
            Route::get('jobs/{jobListing}', [JobListingController::class, 'show']);
            Route::post('jobs/{jobListing}/save', [JobListingController::class, 'save']);
            Route::delete('jobs/{jobListing}/save', [JobListingController::class, 'unsave']);
        });

        // Voice Profile (gated behind feature flag)
        Route::middleware('feature:voice_profile')->group(function () {
            Route::get('voice-profile', [VoiceProfileController::class, 'show']);
            Route::post('voice-profile/samples', [VoiceProfileController::class, 'submitSamples']);
            Route::put('voice-profile', [VoiceProfileController::class, 'update']);
        });

        // Resume Builder (gated behind feature flag)
        Route::middleware('feature:resume_builder')->group(function () {
            Route::get('resumes', [ResumeController::class, 'index']);
            Route::post('resumes/generate', [ResumeController::class, 'generate']);
            Route::get('resumes/{resumeVersion}', [ResumeController::class, 'show']);
            Route::get('resumes/{resumeVersion}/download', [ResumeController::class, 'download']);
            Route::delete('resumes/{resumeVersion}', [ResumeController::class, 'destroy']);
        });

        // Cover Letter Builder (gated behind feature flag)
        Route::middleware('feature:cover_letter_builder')->group(function () {
            Route::get('cover-letters', [CoverLetterController::class, 'index']);
            Route::post('cover-letters/generate', [CoverLetterController::class, 'generate']);
            Route::get('cover-letters/{coverLetter}', [CoverLetterController::class, 'show']);
            Route::get('cover-letters/{coverLetter}/download', [CoverLetterController::class, 'download']);
            Route::delete('cover-letters/{coverLetter}', [CoverLetterController::class, 'destroy']);
        });

        // Resume Conversation Coach (gated behind resume_builder flag)
        Route::middleware('feature:resume_builder')->group(function () {
            Route::post('resume-conversation/start', [ResumeConversationController::class, 'start']);
            Route::post('resume-conversation/message', [ResumeConversationController::class, 'message']);
            Route::get('resume-conversation/history', [ResumeConversationController::class, 'history']);
        });

        // Application Tracking (gated behind feature flag)
        Route::middleware('feature:application_tracking')->group(function () {
            Route::get('applications/analytics', [JobApplicationController::class, 'analytics']);
            Route::get('applications', [JobApplicationController::class, 'index']);
            Route::post('applications', [JobApplicationController::class, 'store']);
            Route::get('applications/{jobApplication}', [JobApplicationController::class, 'show']);
            Route::put('applications/{jobApplication}', [JobApplicationController::class, 'update']);
            Route::delete('applications/{jobApplication}', [JobApplicationController::class, 'destroy']);
            Route::post('applications/{jobApplication}/events', [JobApplicationController::class, 'logEvent']);
        });

        // Career Profile (gated behind feature flag)
        Route::middleware('feature:career_profile')->group(function () {
            Route::get('career-profile', [CareerProfileController::class, 'show']);
            Route::put('career-profile', [CareerProfileController::class, 'update']);
            Route::post('career-profile/import', [CareerProfileController::class, 'import']);
            Route::delete('career-profile', [CareerProfileController::class, 'destroy']);
        });

        // Career Coach Conversation (gated behind feature flag)
        Route::middleware('feature:career_coach')->group(function () {
            Route::post('career-coach/start', [CareerCoachController::class, 'start']);
            Route::post('career-coach/message', [CareerCoachController::class, 'message']);
            Route::get('career-coach/history', [CareerCoachController::class, 'history']);
        });

        // Organization job analytics
        Route::get('organizations/{organization}/job-analytics', function (\App\Models\Organization $organization) {
            return response()->json(
                app(\App\Services\Analytics\OrgJobAnalyticsService::class)->getJobAnalytics($organization)
            );
        });

        // Admin API (platform admins only)
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('stats', [\App\Http\Controllers\Api\V1\AdminStatsController::class, 'index']);
        });
    });
});
