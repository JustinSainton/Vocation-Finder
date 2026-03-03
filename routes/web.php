<?php

use App\Http\Controllers\Web\AssessmentController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\SocialiteController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\CourseController;
use App\Http\Controllers\Web\CurriculumPathwayController;
use App\Http\Controllers\Web\Admin\AdminAssessmentController;
use App\Http\Controllers\Web\Admin\AdminCourseController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminOrganizationController;
use App\Http\Controllers\Web\Admin\AdminQuestionCategoryController;
use App\Http\Controllers\Web\Admin\AdminQuestionController;
use App\Http\Controllers\Web\Admin\AdminUserController;
use App\Http\Controllers\Web\Admin\AdminVocationalCategoryController;
use App\Http\Controllers\Web\Org\OrgDashboardController;
use App\Http\Controllers\Web\Org\OrgMemberController;
use App\Http\Controllers\Web\Org\OrgInsightsController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/assessment', [AssessmentController::class, 'orientation']);
Route::get('/assessment/written', [AssessmentController::class, 'written']);
Route::get('/assessment/{assessment}/results', [AssessmentController::class, 'results']);

// Guest-only routes (redirect if authenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

// Google OAuth
Route::get('/auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [SocialiteController::class, 'callback']);

// Pricing (public)
Route::get('/pricing', [BillingController::class, 'pricing'])->name('pricing');

// Stripe webhooks
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', function () {
        $user = auth()->user();

        $assessments = $user->assessments()
            ->with('vocationalProfile')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'mode' => $a->mode,
                'created_at' => $a->created_at->toISOString(),
            ]);

        // Get latest curriculum pathway
        $pathway = $user->latestCurriculumPathway()
            ?->load('pathwayCourses.enrollment');

        $pathwayData = null;
        if ($pathway) {
            $totalCourses = $pathway->pathwayCourses->count();
            $completedCourses = $pathway->pathwayCourses
                ->filter(fn ($pc) => $pc->enrollment?->status === 'completed')
                ->count();

            $pathwayData = [
                'id' => $pathway->id,
                'status' => $pathway->status,
                'pathway_summary' => $pathway->pathway_summary,
                'total_courses' => $totalCourses,
                'completed_courses' => $completedCourses,
            ];
        }

        return Inertia::render('Dashboard', [
            'assessments' => $assessments,
            'pathway' => $pathwayData,
        ]);
    })->name('dashboard');

    // Billing
    Route::get('/billing', [BillingController::class, 'index'])->name('billing');
    Route::post('/billing/checkout/individual', [BillingController::class, 'checkoutIndividual']);
    Route::post('/billing/checkout/organization', [BillingController::class, 'checkoutOrganization']);
    Route::get('/billing/portal', [BillingController::class, 'billingPortal'])->name('billing.portal');
    Route::get('/billing/success', [BillingController::class, 'checkoutSuccess'])->name('billing.success');

    // Learning pathway
    Route::get('/pathway/{pathway}', [CurriculumPathwayController::class, 'show']);

    // Course enrollment & progress
    Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll']);
    Route::patch('/courses/{course}/progress', [CourseController::class, 'updateProgress']);

    // Admin
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        // Users
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);

        // Organizations
        Route::get('/organizations', [AdminOrganizationController::class, 'index']);
        Route::get('/organizations/create', [AdminOrganizationController::class, 'create']);
        Route::post('/organizations', [AdminOrganizationController::class, 'store']);
        Route::get('/organizations/{organization}', [AdminOrganizationController::class, 'show']);
        Route::get('/organizations/{organization}/edit', [AdminOrganizationController::class, 'edit']);
        Route::put('/organizations/{organization}', [AdminOrganizationController::class, 'update']);

        // Assessments
        Route::get('/assessments', [AdminAssessmentController::class, 'index']);
        Route::get('/assessments/{assessment}', [AdminAssessmentController::class, 'show']);

        // Questions
        Route::get('/questions', [AdminQuestionController::class, 'index']);
        Route::get('/questions/create', [AdminQuestionController::class, 'create']);
        Route::post('/questions', [AdminQuestionController::class, 'store']);
        Route::get('/questions/{question}/edit', [AdminQuestionController::class, 'edit']);
        Route::put('/questions/{question}', [AdminQuestionController::class, 'update']);
        Route::delete('/questions/{question}', [AdminQuestionController::class, 'destroy']);

        // Question Categories
        Route::get('/question-categories', [AdminQuestionCategoryController::class, 'index']);
        Route::get('/question-categories/create', [AdminQuestionCategoryController::class, 'create']);
        Route::post('/question-categories', [AdminQuestionCategoryController::class, 'store']);
        Route::get('/question-categories/{questionCategory}/edit', [AdminQuestionCategoryController::class, 'edit']);
        Route::put('/question-categories/{questionCategory}', [AdminQuestionCategoryController::class, 'update']);
        Route::delete('/question-categories/{questionCategory}', [AdminQuestionCategoryController::class, 'destroy']);

        // Vocational Categories
        Route::get('/vocational-categories', [AdminVocationalCategoryController::class, 'index']);
        Route::get('/vocational-categories/create', [AdminVocationalCategoryController::class, 'create']);
        Route::post('/vocational-categories', [AdminVocationalCategoryController::class, 'store']);
        Route::get('/vocational-categories/{vocationalCategory}/edit', [AdminVocationalCategoryController::class, 'edit']);
        Route::put('/vocational-categories/{vocationalCategory}', [AdminVocationalCategoryController::class, 'update']);
        Route::delete('/vocational-categories/{vocationalCategory}', [AdminVocationalCategoryController::class, 'destroy']);

        // Courses
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::get('/courses/create', [AdminCourseController::class, 'create']);
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}/edit', [AdminCourseController::class, 'edit']);
        Route::put('/courses/{course}', [AdminCourseController::class, 'update']);
        Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy']);
    });
});

// Organization admin
Route::prefix('org/{organization}')->middleware(['auth', 'org.admin'])->group(function () {
    Route::get('/', [OrgDashboardController::class, 'index']);
    Route::get('/members', [OrgMemberController::class, 'index']);
    Route::post('/members/invite', [OrgMemberController::class, 'invite']);
    Route::delete('/members/{user}', [OrgMemberController::class, 'remove']);
    Route::get('/members/{user}', [OrgMemberController::class, 'show']);
    Route::get('/insights', [OrgInsightsController::class, 'index']);
});

// Courses (public)
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/modules/{module}', [CourseController::class, 'module']);
