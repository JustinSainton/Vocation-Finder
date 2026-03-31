<?php

use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssessmentSurveyController;
use App\Http\Controllers\Api\V1\AudioConversationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\PathwayController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\ResultsController;
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
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

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
    });
});
