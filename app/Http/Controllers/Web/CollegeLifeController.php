<?php

namespace App\Http\Controllers\Web;

use App\Enums\MilestoneKind;
use App\Http\Controllers\Controller;
use App\Jobs\ParseSyllabusJob;
use App\Models\Enrollment;
use App\Models\Milestone;
use App\Models\Syllabus;
use App\Support\CampusGuide;
use App\Support\IcsFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Being in college, per roadmap 4.4.
 *
 * The last layer the vision names, and the one that decides whether any of the
 * rest mattered: a student who enrols and leaves in the first year is worse
 * off than one who never went, because they have the debt and not the degree.
 *
 * Under `/plan` with the college explorer and vetted work — still five places.
 * Coursework does not get a list of its own either: syllabus deadlines become
 * {@see Milestone}s of kind {@see MilestoneKind::Academic} in the plan the
 * student already reads, because a second dated list is a second plan
 * competing with the first.
 */
class CollegeLifeController extends Controller
{
    public function __construct(
        protected CampusGuide $guide = new CampusGuide,
        protected IcsFeed $feed = new IcsFeed,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $enrollment = $this->guide->currentEnrollment($student);

        return Inertia::render('CollegeLife/Index', [
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'college_name' => $enrollment->college_name,
                'program' => $enrollment->program,
                'started_on' => $enrollment->started_on?->toDateString(),
            ] : null,
            'resources' => $this->guide->resources($enrollment?->college),
            'syllabi' => $enrollment
                ? $enrollment->syllabi()->latest()->get()->map(fn (Syllabus $syllabus) => [
                    'id' => $syllabus->id,
                    'course_code' => $syllabus->course_code,
                    'course_title' => $syllabus->course_title,
                    'term' => $syllabus->term,
                    'parsed' => $syllabus->parsed_at !== null,
                    'kept' => $syllabus->milestones()->count(),
                    /*
                     | The count of what we could not read, never the student's
                     | own words back at them as errors. A parser that handles
                     | a format badly should be visible to the person it
                     | failed, so they know to check the syllabus themselves
                     | rather than trusting a calendar that quietly lost a
                     | midterm.
                     */
                    'discarded' => count($syllabus->discarded ?? []),
                ])->values()->all()
                : [],
            'coursework' => Milestone::query()
                ->where('user_id', $student->id)
                ->whereNotNull('syllabus_id')
                ->orderBy('due_on')
                ->get()
                ->map(fn (Milestone $milestone) => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'due_on' => $milestone->due_on?->toDateString(),
                    'status' => $milestone->status->value,
                ])->values()->all(),
            'calendar_url' => route('calendar.feed', ['token' => $this->feed->tokenFor($student)]),
        ]);
    }

    /**
     * The student says where they are.
     *
     * `college_name` is required and `college_id` is optional on purpose: a
     * student at a college nobody has imported yet is exactly the student this
     * layer is for, and locking the whole surface behind our own reference
     * data would shut them out of it.
     */
    public function storeEnrollment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_name' => ['required', 'string', 'max:255'],
            'college_id' => ['nullable', 'uuid', Rule::exists('colleges', 'id')],
            'program' => ['nullable', 'string', 'max:255'],
            'started_on' => ['nullable', 'date'],
        ]);

        $request->user()->enrollments()->create([
            ...$validated,
            'started_on' => $validated['started_on'] ?? now()->toDateString(),
        ]);

        return back();
    }

    public function endEnrollment(Request $request, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->user_id === $request->user()->id, 404);

        $enrollment->forceFill(['ended_at' => now()])->save();

        return back();
    }

    /**
     * Take in a syllabus and queue the parse.
     *
     * The text is stored before anything is parsed from it, so provenance can
     * be checked against the document later and a failed parse loses nothing.
     */
    public function storeSyllabus(Request $request): RedirectResponse
    {
        $student = $request->user();

        $validated = $request->validate([
            'course_code' => ['nullable', 'string', 'max:40'],
            'course_title' => ['nullable', 'string', 'max:255'],
            'term' => ['nullable', 'string', 'max:40'],
            'source_text' => ['required', 'string', 'min:40', 'max:200000'],
        ]);

        $syllabus = $student->syllabi()->create([
            ...$validated,
            'enrollment_id' => $this->guide->currentEnrollment($student)?->id,
        ]);

        ParseSyllabusJob::dispatch($syllabus);

        return back();
    }

    /**
     * Break the old calendar link.
     *
     * A subscribable URL is a secret that lives in other people's devices, and
     * a student who has shared a screen or lost a phone needs a way to end it
     * that does not involve asking us.
     */
    public function rotateCalendar(Request $request): RedirectResponse
    {
        $this->feed->rotate($request->user());

        return back();
    }
}
