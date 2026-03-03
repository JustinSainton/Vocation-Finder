<?php

use App\Http\Controllers\Web\AssessmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/assessment', [AssessmentController::class, 'orientation']);
Route::get('/assessment/written', [AssessmentController::class, 'written']);
Route::get('/assessment/{assessment}/results', [AssessmentController::class, 'results']);
