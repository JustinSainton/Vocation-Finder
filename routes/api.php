<?php

use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AudioConversationController;
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

    // Public — no auth required
    Route::get('questions', [QuestionController::class, 'index']);

    // Assessment — works for both authenticated and guest users
    Route::post('assessments', [AssessmentController::class, 'store']);
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);
    Route::post('assessments/{assessment}/answers', [AssessmentController::class, 'saveAnswer']);
    Route::patch('assessments/{assessment}/answers/{answer}', [AssessmentController::class, 'updateAnswer']);
    Route::post('assessments/{assessment}/complete', [AssessmentController::class, 'complete']);

    // Results
    Route::get('assessments/{assessment}/results', [ResultsController::class, 'show']);
    Route::post('assessments/{assessment}/results/email', [ResultsController::class, 'email']);

    // Audio conversation
    Route::post('conversations/start', [AudioConversationController::class, 'start']);
    Route::post('conversations/{session}/audio', [AudioConversationController::class, 'uploadAudio']);
    Route::post('conversations/{session}/turn', [AudioConversationController::class, 'processTurn']);
    Route::post('conversations/{session}/complete', [AudioConversationController::class, 'complete']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('assessments', function (Request $request) {
            return $request->user()->assessments()
                ->with('vocationalProfile')
                ->latest()
                ->paginate(10);
        });
    });
});
