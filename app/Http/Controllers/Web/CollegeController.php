<?php

namespace App\Http\Controllers\Web;

use App\Enums\IncomeBand;
use App\Http\Controllers\Controller;
use App\Models\College;
use App\Support\CollegeApplication;
use App\Support\CollegeCost;
use App\Support\CollegeExplorer;
use App\Support\CollegeStanding;
use App\Support\ConversationPrompts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The college explorer, per roadmap 4.2.
 *
 * It lives under `/plan` rather than becoming a sixth place in the navigation.
 * The vision names five places and 3.1 made that list derive from the router,
 * so a sixth tab would quietly break a promise the product makes about its own
 * shape. College choice is a section of the plan — which is exactly what the
 * vision calls it: "their life in sections. If someone is heading to college,
 * they see the next four years."
 *
 * Nothing here applies on a student's behalf. The only writes are to the
 * student's own list.
 */
class CollegeController extends Controller
{
    public function __construct(
        protected CollegeExplorer $explorer = new CollegeExplorer,
        protected CollegeStanding $standing = new CollegeStanding,
        protected CollegeCost $cost = new CollegeCost,
        protected CollegeApplication $application = new CollegeApplication,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();

        $filters = $request->only(['state', 'kind', 'max_cost', 'standing', 'category']);

        $list = $student->collegeInterests()->orderBy('name')->get();

        return Inertia::render('Colleges/Index', [
            'filters' => $filters,
            'results' => $this->explorer->results($student, $filters),
            'list' => $list->map(fn (College $college) => [
                'id' => $college->id,
                'slug' => $college->slug,
                'name' => $college->name,
                'standing' => $this->standing->for($student, $college)->label(),
            ])->all(),
            /*
             | The only thing said about the list as a whole, and it is about
             | the list's shape rather than its quality. See
             | CollegeStanding::balanceAdvice().
             */
            'balance' => $this->standing->balanceAdvice($student, $list),
            /*
             | What we still need from the student before any of the numbers
             | mean anything. Asked for here rather than demanded at a gate:
             | the explorer works without either, it just says less.
             */
            'knows_gpa' => $student->gpa !== null,
            'knows_income_band' => $student->household_income_band !== null,
        ]);
    }

    public function show(Request $request, College $college): Response
    {
        $student = $request->user();
        $college->load('vocationalCategories');
        $standing = $this->standing->for($student, $college);

        return Inertia::render('Colleges/Show', [
            'college' => [
                'id' => $college->id,
                'slug' => $college->slug,
                'name' => $college->name,
                'where' => $college->location(),
                'kind' => $college->kind->label(),
                'control' => $college->control->label(),
                'test_optional' => $college->test_optional,
                'application_url' => $college->application_url,
                'programs' => $college->vocationalCategories
                    ->map(fn ($category) => [
                        'name' => $category->pivot->program_name,
                        'url' => $category->pivot->program_url,
                    ])->all(),
            ],
            'standing' => [
                'value' => $standing->value,
                'label' => $standing->label(),
                'description' => $standing->description(),
            ],
            'cost' => $this->cost->for($student, $college),
            'steps' => $this->application->steps($student, $college),
            'deadline' => $this->application->deadline($college)?->toDateString(),
            'on_list' => $student->collegeInterests()->where('colleges.id', $college->id)->exists(),
            /*
             | The vision is explicit that college choice "isn't meant to be
             | self-serve, it's meant to pull the family into the decision".
             | So every college page hands the student a question to take to
             | somebody, from the same reviewed catalogue the family report
             | draws on.
             */
            'conversation' => (new ConversationPrompts)->forCollege($student, $college->name),
        ]);
    }

    /**
     * Put a college on the list. The student's act, and reversible.
     */
    public function store(Request $request, College $college): RedirectResponse
    {
        $request->user()->collegeInterests()->syncWithoutDetaching([$college->id => []]);

        return back();
    }

    public function destroy(Request $request, College $college): RedirectResponse
    {
        $request->user()->collegeInterests()->detach($college->id);

        return back();
    }

    /**
     * The two facts the student supplies, stored exactly as given.
     *
     * Validated hard because both feed arithmetic a student will make a real
     * decision on: a GPA outside 0–4.0 would produce a standing that is simply
     * wrong, and an income figure instead of a bracket is data we have no
     * business holding.
     */
    public function updateInputs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:4.0'],
            'household_income_band' => ['nullable', 'string', 'in:'.implode(',', IncomeBand::values())],
            'home_state' => ['nullable', 'string', 'size:2'],
        ]);

        $request->user()->update($validated);

        return back();
    }
}
