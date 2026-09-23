<?php

use App\Http\Controllers\Web\Admin\AdminAssessmentController;
use App\Http\Controllers\Web\Admin\AdminCourseController;
use App\Http\Controllers\Web\Admin\AdminCourseMediaController;
use App\Http\Controllers\Web\Admin\AdminCurriculumImportController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminFeatureFlagController;
use App\Http\Controllers\Web\Admin\AdminJobListingController;
use App\Http\Controllers\Web\Admin\AdminOrganizationController;
use App\Http\Controllers\Web\Admin\AdminPortraitReviewController;
use App\Http\Controllers\Web\Admin\AdminQuestionCategoryController;
use App\Http\Controllers\Web\Admin\AdminQuestionController;
use App\Http\Controllers\Web\Admin\AdminUserController;
use App\Http\Controllers\Web\Admin\AdminValidationController;
use App\Http\Controllers\Web\Admin\AdminVocationalCategoryController;
use App\Http\Controllers\Web\ApplicationController;
use App\Http\Controllers\Web\AssessmentController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\SocialiteController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\BrainController;
use App\Http\Controllers\Web\BrainExportController;
use App\Http\Controllers\Web\CalendarFeedController;
use App\Http\Controllers\Web\CareerProfileController;
use App\Http\Controllers\Web\ClarityCheckController;
use App\Http\Controllers\Web\CollegeController;
use App\Http\Controllers\Web\CollegeLifeController;
use App\Http\Controllers\Web\CourseController;
use App\Http\Controllers\Web\CurriculumPathwayController;
use App\Http\Controllers\Web\EvaluationFeedbackController;
use App\Http\Controllers\Web\FamilyReportController;
use App\Http\Controllers\Web\FirstRunController;
use App\Http\Controllers\Web\HabitCheckInController;
use App\Http\Controllers\Web\HabitController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\JobController;
use App\Http\Controllers\Web\LockerController;
use App\Http\Controllers\Web\MilestoneController;
use App\Http\Controllers\Web\Org\OrgCohortController;
use App\Http\Controllers\Web\Org\OrgDashboardController;
use App\Http\Controllers\Web\Org\OrgInsightsController;
use App\Http\Controllers\Web\Org\OrgMemberController;
use App\Http\Controllers\Web\Org\OrgMentorController;
use App\Http\Controllers\Web\Org\OrgMentorNoteController;
use App\Http\Controllers\Web\Org\OrgSettingsController;
use App\Http\Controllers\Web\ParentConsentController;
use App\Http\Controllers\Web\PathwayCoachController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\Web\ResumeController;
use App\Http\Controllers\Web\WorkController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

// A parent says yes on their own token link. Deliberately outside auth:
// requiring a parent to create an account adds a step that loses exactly the
// students whose families are busiest.
// The plan as a calendar a phone can subscribe to, per 4.4. Outside `auth`
// because a calendar client cannot hold a session, and outside every
// entitlement check for the same reason the brain export is. The token is the
// whole of the authorisation and the student can rotate it.
Route::get('/calendar/{token}.ics', CalendarFeedController::class)->name('calendar.feed');

Route::get('/consent/{token}', [ParentConsentController::class, 'show'])->name('parent-consent.show');
Route::post('/consent/{token}', [ParentConsentController::class, 'grant'])->name('parent-consent.grant');
Route::delete('/consent/{token}', [ParentConsentController::class, 'revoke'])->name('parent-consent.revoke');

// The same token, the same reasoning: a parent already holding this link is
// the person consent was given by. Revoking consent closes this door with it.
Route::get('/family/{token}', [FamilyReportController::class, 'show'])->name('family.report');

// A student invited by their school, arriving with no account. Outside `auth`
// for the obvious reason: a login wall here means the link only works for the
// people who did not need it.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::get('/assessment', [AssessmentController::class, 'orientation']);
Route::get('/assessment/written', [AssessmentController::class, 'written']);
Route::get('/assessment/{assessment}/results', [AssessmentController::class, 'results']);

/**
 * Roadmap 0.5. Open to guests for the same reason the assessment is: gating
 * this behind a login would sample only the students it already worked for.
 */
Route::post('/assessment/{assessment}/feedback', [EvaluationFeedbackController::class, 'store'])
    ->name('evaluation-feedback.store');

// Clarity, read at both ends of the assessment, per 5.1. Open to guests for
// the same reason the feedback route is: requiring an account first samples
// only the people it worked for.
Route::post('/assessment/{assessment}/clarity', [ClarityCheckController::class, 'store'])
    ->name('clarity-check.store');

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
        $pathway = $user->latestCurriculumPathway;
        $pathway?->load('pathwayCourses.enrollment');

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

    Route::post('/billing/checkout/student', [BillingController::class, 'checkoutStudent'])->name('billing.checkout.student');

    // The first-run sequence: landing -> assessment -> account -> results ->
    // age gate -> checkout -> refinement -> first action. The step is computed
    // from the student's own data, never stored, so it cannot go stale.
    Route::get('/next', [FirstRunController::class, 'show'])->name('first-run');

    // Always-on export. Deliberately outside the pathway_coach flag and every
    // entitlement check: a lapse freezes the brain, it does not seize it, and
    // we promise parents in writing that nothing is ever deleted.
    Route::get('/brain/export', BrainExportController::class)->name('brain.export');

    /*
     | The brain, read on a page. Outside `feature:pathway_coach` and outside
     | every entitlement check, deliberately and for the same reason the
     | export above is: a lapse freezes the brain, it does not seize it.
     | Allowing a student to download their own sentences while refusing to
     | show them is that seizure with an extra step.
     */
    Route::get('/brain', [BrainController::class, 'index'])->name('brain');

    Route::post('/parent-consent', [ParentConsentController::class, 'store'])->name('parent-consent.store');

    // The pathway coach is the front door for students, not a feature tab.
    // Gated behind its own flag so it can be dark-launched independently of
    // the adult career coach, which is a different product for a different
    // person.
    Route::middleware('feature:pathway_coach')->group(function () {
        Route::get('/coach', [PathwayCoachController::class, 'index'])->name('coach');
        Route::post('/coach/message', [PathwayCoachController::class, 'message'])->name('coach.message');
        Route::post('/coach/stream', [PathwayCoachController::class, 'stream'])->middleware('throttle:30,1')->name('coach.stream');
        Route::post('/coach/open', [PathwayCoachController::class, 'open'])->middleware('throttle:10,1')->name('coach.open');

        /*
         | Checking in is the student's act and nobody else's. There is
         | deliberately no route by which a coach, counsellor or parent
         | records that a habit happened — a tracker someone else can fill in
         | is a compliance report.
         */
        Route::post('/habits/{habit}/check-in', [HabitCheckInController::class, 'store'])->name('habits.check-in');

        // Habits as a place of their own, per 3.1, and not only a section on
        // the coach page.
        Route::get('/habits', [HabitController::class, 'index'])->name('habits');

        // The plan, per 3.2. Registering this route is the whole of making it
        // appear in the navigation — see App\Enums\StudentPlace.
        Route::get('/plan', [PlanController::class, 'index'])->name('plan');

        /*
         | The college explorer, per 4.2. Registered under `/plan` rather than
         | as a place of its own: the vision names five places and 3.1 made
         | the navigation derive from this router, so a sixth top-level tab
         | would silently change the shape the product promises. College
         | choice is a section of the plan.
         |
         | There is no route here that submits an application, and there is
         | not going to be one — "the tool will not apply for them" is the
         | vision's own sentence, and the only writes below are a student
         | adding a school to their own list or telling us their GPA.
         */
        Route::get('/plan/colleges', [CollegeController::class, 'index'])->name('colleges');
        Route::get('/plan/colleges/{college:slug}', [CollegeController::class, 'show'])->name('colleges.show');
        Route::post('/plan/colleges/{college:slug}/list', [CollegeController::class, 'store'])->name('colleges.list.store');
        Route::delete('/plan/colleges/{college:slug}/list', [CollegeController::class, 'destroy'])->name('colleges.list.destroy');
        Route::patch('/plan/colleges/inputs', [CollegeController::class, 'updateInputs'])->name('colleges.inputs');

        /*
         | Vetted work, per 4.3. Beside the college explorer rather than
         | anywhere near the legacy `/jobs` board, which was built for adults
         | who chose to look at a job board and has no age gate of any kind.
         |
         | Both routes read App\Support\StudentJobs and nothing else. There is
         | no route that submits an application, and a listing this student may
         | not see 404s rather than 403s — naming a posting we rejected as
         | fraudulent hands a curious teenager the thing to go and search for.
         */
        /*
         | Being in college, per 4.4. The last layer the vision names and the
         | one that decides whether the rest mattered — a student who enrols
         | and leaves in the first year has the debt and not the degree.
         |
         | Still under `/plan`, still five places. Syllabus deadlines do not
         | get a list of their own either: they become milestones of kind
         | `academic` in the plan the student already reads.
         */
        Route::get('/plan/college-life', [CollegeLifeController::class, 'index'])->name('college-life');
        Route::post('/plan/college-life/enrollment', [CollegeLifeController::class, 'storeEnrollment'])->name('college-life.enrollment.store');
        Route::delete('/plan/college-life/enrollment/{enrollment}', [CollegeLifeController::class, 'endEnrollment'])->name('college-life.enrollment.end');
        Route::post('/plan/college-life/syllabus', [CollegeLifeController::class, 'storeSyllabus'])->name('college-life.syllabus.store');
        Route::post('/plan/calendar/rotate', [CollegeLifeController::class, 'rotateCalendar'])->name('calendar.rotate');

        Route::get('/plan/work', [WorkController::class, 'index'])->name('work');
        Route::get('/plan/work/{jobListing}', [WorkController::class, 'show'])->name('work.show');

        /*
         | Moving a milestone is the student's act, exactly as checking in on
         | a habit is. No route here takes another person's id.
         */
        Route::patch('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');

        /*
         | The locker, per 3.3. Files live on a private disk and come back
         | only through `locker.download`, which checks ownership — never
         | from `public/`, where a guessable URL would put a minor's essay in
         | front of anyone who tried one.
         */
        Route::get('/locker', [LockerController::class, 'index'])->name('locker');
        Route::post('/locker', [LockerController::class, 'store'])->name('locker.store');
        Route::get('/locker/{artifact}/download', [LockerController::class, 'download'])->name('locker.download');
        Route::delete('/locker/{artifact}', [LockerController::class, 'destroy'])->name('locker.destroy');
    });

    // Career Coach (gated behind feature flag)
    Route::middleware('feature:career_coach')->group(function () {
        Route::get('/career-coach', fn () => Inertia::render('CareerCoach/Index'));
    });

    // Job Discovery (gated behind feature flag)
    Route::middleware('feature:job_discovery')->group(function () {
        Route::get('/jobs', [JobController::class, 'index']);
        Route::get('/jobs/{jobListing}', [JobController::class, 'show']);
        Route::post('/jobs/{jobListing}/save', [JobController::class, 'save']);
        Route::delete('/jobs/{jobListing}/save', [JobController::class, 'unsave']);
    });

    // Application Tracking (gated behind feature flag)
    Route::middleware('feature:application_tracking')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::get('/applications/{jobApplication}', [ApplicationController::class, 'show']);
    });

    // Resumes & Cover Letters (gated behind feature flag)
    Route::middleware('feature:resume_builder')->group(function () {
        Route::get('/resumes', [ResumeController::class, 'index']);
        Route::get('/resumes/conversation', [ResumeController::class, 'conversation']);
        Route::get('/resumes/{resumeVersion}', [ResumeController::class, 'show']);
    });

    // Career Profile (gated behind feature flag)
    Route::middleware('feature:career_profile')->group(function () {
        Route::get('/career-profile', [CareerProfileController::class, 'index']);
        Route::get('/career-profile/import', [CareerProfileController::class, 'import']);
        Route::post('/career-profile/import', [CareerProfileController::class, 'storeImport']);
        Route::put('/career-profile', [CareerProfileController::class, 'update']);
        Route::delete('/career-profile', [CareerProfileController::class, 'destroy']);
    });

    // Voice Profile (requires both career_profile and voice_profile flags)
    Route::middleware(['feature:career_profile', 'feature:voice_profile'])->group(function () {
        Route::get('/career-profile/voice', [CareerProfileController::class, 'voiceProfile']);
    });

    /**
     * Courses and the learning pathway built on them are a legacy surface that
     * V1 does not ship. Every other legacy surface — jobs, resumes, cover
     * letters, application tracking — already sits behind a flag that defaults
     * off; these two were the exception, and so were reachable by every
     * student on day one of a product that has no published courses.
     */
    Route::middleware('feature:courses')->group(function () {
        Route::get('/pathway/{pathway}', [CurriculumPathwayController::class, 'show']);

        Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll']);
        Route::patch('/courses/{course}/progress', [CourseController::class, 'updateProgress']);
    });

    // Admin
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        /*
         | The validation read-out, per 5.2. Admin-only on purpose: these are
         | numbers about whether the product works, and a student shown them is
         | being handed the company's self-assessment instead of their result.
         */
        Route::get('/validation', [AdminValidationController::class, 'index'])->name('admin.validation');

        /*
         | Human review of portraits, per 5.3. There is no route that opens a
         | chosen portrait: the reviewer is handed the next one by
         | App\Support\ReviewQueue. A queue people pick from becomes a queue of
         | interesting cases, and the failure this product actually has is the
         | fluent, plausible, slightly wrong portrait nobody volunteers to read.
         */
        Route::get('/reviews', [AdminPortraitReviewController::class, 'index'])->name('admin.reviews');
        Route::post('/reviews/{vocationalProfile}', [AdminPortraitReviewController::class, 'store'])->name('admin.reviews.store');

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

        // Course Media
        Route::post('/course-media', [AdminCourseMediaController::class, 'store']);
        Route::get('/course-media/{courseMedia}', [AdminCourseMediaController::class, 'show']);
        Route::delete('/course-media/{courseMedia}', [AdminCourseMediaController::class, 'destroy']);

        // Curriculum Import
        Route::post('/curriculum-import', [AdminCurriculumImportController::class, 'store']);
        Route::get('/curriculum-import/{curriculumImport}', [AdminCurriculumImportController::class, 'show']);
        Route::post('/curriculum-import/{curriculumImport}/confirm', [AdminCurriculumImportController::class, 'confirm']);

        // Feature Flags
        Route::get('/feature-flags', [AdminFeatureFlagController::class, 'index']);
        Route::put('/feature-flags/{featureFlag}', [AdminFeatureFlagController::class, 'update']);

        // Job Listings
        Route::get('/jobs', [AdminJobListingController::class, 'index']);
    });
});

/*
 | The organization surface is addressed by slug — every link in `OrgLayout`
 | is `/org/{slug}` — and was binding on the UUID primary key, so none of
 | these routes reached their controller. Bound per-route rather than with
 | `getRouteKeyName()`, because the platform-admin surface at
 | `/admin/organizations/{organization}` addresses the same model by id.
 */

// Organization — admin-only routes
Route::prefix('org/{organization:slug}')->middleware(['auth', 'org.role:admin'])->group(function () {
    Route::get('/', [OrgDashboardController::class, 'index']);
    Route::get('/members', [OrgMemberController::class, 'index']);
    Route::post('/members/invite', [OrgMemberController::class, 'invite']);
    Route::delete('/members/{user}', [OrgMemberController::class, 'remove']);
    Route::get('/settings', [OrgSettingsController::class, 'edit'])->name('org.settings');
    Route::put('/settings', [OrgSettingsController::class, 'update']);
    Route::get('/mentors', [OrgMentorController::class, 'index']);
    Route::post('/mentors/assign', [OrgMentorController::class, 'assign']);
    Route::delete('/mentors/{assignment}', [OrgMentorController::class, 'unassign']);
});

// Organization — admin and mentor routes
Route::prefix('org/{organization:slug}')->middleware(['auth', 'org.role:admin,mentor'])->group(function () {
    Route::get('/members/{user}', [OrgMemberController::class, 'show']);
    Route::get('/insights', [OrgInsightsController::class, 'index']);
    Route::get('/cohort', [OrgCohortController::class, 'index'])->name('org.cohort');
    Route::post('/members/{member}/notes', [OrgMentorNoteController::class, 'store']);
    Route::patch('/notes/{note}', [OrgMentorNoteController::class, 'update']);
    Route::delete('/notes/{note}', [OrgMentorNoteController::class, 'destroy']);
});

// Courses (public)
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/modules/{module}', [CourseController::class, 'module']);
