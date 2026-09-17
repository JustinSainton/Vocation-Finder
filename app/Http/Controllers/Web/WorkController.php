<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Support\ConversationPrompts;
use App\Support\JobApplicationGuide;
use App\Support\StudentJobs;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vetted work, per roadmap 4.3.
 *
 * Under `/plan` beside the college explorer, for the same reason: the vision
 * names five places, and work is a section of the plan rather than a sixth
 * tab. A student choosing between an apprenticeship and a degree should find
 * both in the same part of the product, because presenting them in different
 * places is itself an argument about which one is the real option.
 *
 * Every listing on every response here comes through {@see StudentJobs}, which
 * is the only thing permitted to decide what a given student may see. The
 * controller does no filtering of its own — a second query is a second place
 * that can forget the age check.
 */
class WorkController extends Controller
{
    public function __construct(
        protected StudentJobs $jobs = new StudentJobs,
        protected JobApplicationGuide $guide = new JobApplicationGuide,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $filters = $request->only(['kind', 'remote', 'category', 'search']);

        $listings = $this->jobs->visibleTo($student, $filters);

        return Inertia::render('Work/Index', [
            'filters' => $filters,
            'results' => $listings->map(fn (JobListing $listing) => $this->summary($listing))->values()->all(),
        ]);
    }

    public function show(Request $request, JobListing $jobListing): Response
    {
        $student = $request->user();

        /*
         | A 404 rather than a 403. Telling somebody that a listing exists but
         | is not for them is still telling them it exists, and a rejected
         | posting is one we have decided is fraudulent — naming it hands a
         | curious sixteen-year-old the thing to go and search for.
         */
        if (! $this->jobs->mayOpen($student, $jobListing)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Work/Show', [
            'listing' => array_merge($this->summary($jobListing), [
                'description' => $jobListing->description_plain ?: strip_tags((string) $jobListing->description),
                'source_url' => $jobListing->source_url,
                'company_url' => $jobListing->company_url,
                'kind_description' => $jobListing->work_kind->description(),
            ]),
            'steps' => $this->guide->steps($student, $jobListing),
            'questions' => $this->guide->questionsToAsk($student, $jobListing),
            'conversation' => (new ConversationPrompts)->forWork($student, $jobListing->company_name),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(JobListing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'company_name' => $listing->company_name,
            'location' => $listing->location,
            'is_remote' => $listing->is_remote,
            'kind' => $listing->work_kind->label(),
            'minimum_age' => $listing->minimum_age,
            'supervised' => $listing->supervised,
            'pay' => $this->pay($listing),
            /*
             | The vetting findings are deliberately NOT shipped. A student
             | does not need our reasoning about a posting that passed, and a
             | posting that failed never reaches this method at all. Shipping
             | the warnings would turn the list into a page of caveats and
             | teach students to read past them.
             */
        ];
    }

    /**
     * Pay as a sentence, or an honest absence.
     *
     * A posting with no pay stated says so out loud, because "salary not
     * listed" rendered as a blank is how a student finds out on day one that
     * it is unpaid.
     */
    protected function pay(JobListing $listing): string
    {
        if ($listing->salary_min === null && $listing->salary_max === null) {
            return 'They have not said what it pays. Ask before you commit any time to it.';
        }

        $money = fn (?int $value) => $value === null ? null : '$'.number_format($value);

        return match (true) {
            $listing->salary_min !== null && $listing->salary_max !== null => $money($listing->salary_min).' to '.$money($listing->salary_max),
            $listing->salary_min !== null => 'From '.$money($listing->salary_min),
            default => 'Up to '.$money($listing->salary_max),
        };
    }
}
