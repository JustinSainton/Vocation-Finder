<?php

namespace Tests\Feature;

use App\Enums\AdmissionStanding;
use App\Enums\CollegeControl;
use App\Enums\CollegeKind;
use App\Enums\IncomeBand;
use App\Enums\StudentPlace;
use App\Models\Assessment;
use App\Models\College;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Models\VocationalCategory;
use App\Models\VocationalProfile;
use App\Support\CollegeApplication;
use App\Support\CollegeCost;
use App\Support\CollegeStanding;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The college layer, and the four ways it could go wrong.
 *
 * It could turn admissions into a score. It could repeat the sticker price and
 * frighten off the students it exists for. It could apply on a student's
 * behalf. And it could surface schools for reasons nobody can explain. Each is
 * asserted here against the payload the server actually sends.
 */
class CollegeExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    protected function senior(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'birthdate' => now()->subYears(19),
            'grade_level' => 12,
            'gpa' => 3.50,
            'home_state' => 'OH',
            'household_income_band' => IncomeBand::From30kTo48k,
        ], $attributes));
    }

    /**
     * A GPA below the published middle half is a reach, inside it is in range,
     * at or above it is likely. The boundaries are the college's own published
     * figures, which is what makes the answer checkable against their common
     * data set rather than against our opinion.
     */
    #[Test]
    public function standing_comes_from_the_published_range_and_nothing_else(): void
    {
        $student = $this->senior(['gpa' => 3.50]);
        $college = College::factory()->create(['gpa_25th' => 3.20, 'gpa_75th' => 3.80]);
        $standing = new CollegeStanding;

        $this->assertSame(AdmissionStanding::Possible, $standing->for($student, $college));

        $student->update(['gpa' => 3.00]);
        $this->assertSame(AdmissionStanding::Reach, $standing->for($student->fresh(), $college));

        $student->update(['gpa' => 3.95]);
        $this->assertSame(AdmissionStanding::Likely, $standing->for($student->fresh(), $college));
    }

    /**
     * An open-admission community college publishes no range. That must read
     * as "not enough to say" — a missing range silently becoming a zero would
     * make every student look admissible everywhere, which is the failure that
     * costs somebody a year.
     */
    #[Test]
    public function a_school_with_no_published_range_says_so(): void
    {
        $student = $this->senior();
        $college = College::factory()->openAdmission()->create();

        $this->assertSame(AdmissionStanding::Unknown, (new CollegeStanding)->for($student, $college));

        $unknown = $this->senior(['gpa' => null]);
        $this->assertSame(
            AdmissionStanding::Unknown,
            (new CollegeStanding)->for($unknown, College::factory()->create()),
        );
    }

    /**
     * ⚠️ The invariant DESIGN.md exists to protect. Admissions is the single
     * most tempting place in the product to render a percentage, and every
     * competitor does. Nothing about where a student stands may carry a
     * number, a rank or a match score.
     */
    #[Test]
    public function where_a_student_stands_is_never_a_number(): void
    {
        foreach (AdmissionStanding::cases() as $standing) {
            foreach ([$standing->label(), $standing->description(), $standing->listAdvice()] as $copy) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\d+\s?%|\bpercent|\bchance of\b|\bodds\b|\bscore\b|\branked?\b/i',
                    $copy,
                    "Standing copy reads as a prediction — {$copy}",
                );
            }
        }

        $this->enableCoach();
        $student = $this->senior();
        College::factory()->create();

        $props = $this->actingAs($student)->get('/plan/colleges')->viewData('page')['props'];
        $keys = $this->keysIn($props['results']);

        foreach (['score', 'percent', 'probability', 'rank', 'rating', 'match', 'chance'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "The explorer ships a '{$forbidden}' key.");
        }
    }

    /**
     * Money decides what a place costs, never whether somebody is good enough
     * to be admitted. A standing that moved with income would teach a student
     * that being poor lowers their chances.
     */
    #[Test]
    public function money_never_touches_the_admissions_answer(): void
    {
        $college = College::factory()->create(['gpa_25th' => 3.20, 'gpa_75th' => 3.80]);
        $standing = new CollegeStanding;

        $poor = $this->senior(['household_income_band' => IncomeBand::UpTo30k]);
        $rich = $this->senior(['household_income_band' => IncomeBand::Over110k]);

        $this->assertSame($standing->for($poor, $college), $standing->for($rich, $college));
    }

    /**
     * The sticker price is what stops students applying and is almost never
     * what they pay. The headline figure must be the net price for their own
     * bracket, looked up rather than estimated.
     */
    #[Test]
    public function the_cost_shown_is_what_people_in_their_bracket_actually_paid(): void
    {
        $student = $this->senior(['household_income_band' => IncomeBand::UpTo30k]);
        $college = College::factory()->create([
            'tuition_out_of_state' => 38000,
            'room_and_board' => 12000,
            'net_price_by_income' => [IncomeBand::UpTo30k->value => 14000],
        ]);

        $cost = (new CollegeCost)->for($student, $college);

        $this->assertSame(14000, $cost['estimated']);
        $this->assertSame(50000, $cost['published']);
        $this->assertStringContainsString('$36,000 less', $cost['caveat']);
    }

    /**
     * A student who will not type an income bracket still gets the explorer.
     * The published price is shown with a sentence saying what it is, rather
     * than silently standing in for an answer we do not have.
     */
    #[Test]
    public function an_unknown_bracket_is_said_out_loud_rather_than_guessed(): void
    {
        $student = $this->senior(['household_income_band' => null]);
        $cost = (new CollegeCost)->for($student, College::factory()->create());

        $this->assertNull($cost['estimated']);
        $this->assertStringContainsString('almost never what a family pays', $cost['caveat']);
    }

    /**
     * A bracket the school suppressed must not become zero. A $0 net price is
     * the most consequential wrong number this product could print.
     */
    #[Test]
    public function a_missing_net_price_is_never_zero(): void
    {
        $student = $this->senior(['household_income_band' => IncomeBand::Over110k]);
        $college = College::factory()->create([
            'net_price_by_income' => [IncomeBand::UpTo30k->value => 14000],
        ]);

        $cost = (new CollegeCost)->for($student, $college);

        $this->assertNull($cost['estimated']);
        $this->assertNotSame(0, $cost['estimated']);
        $this->assertStringContainsString('has not published', $cost['caveat']);
    }

    /**
     * A public school charges two prices. Showing an Ohio student the
     * out-of-state figure for an Ohio school overstates the cost by a factor
     * that changes what they apply to.
     */
    #[Test]
    public function residency_decides_which_published_price_is_shown(): void
    {
        $college = College::factory()->openAdmission()->create([
            'state' => 'OH',
            'tuition_in_state' => 4200,
            'tuition_out_of_state' => 9800,
        ]);
        $cost = new CollegeCost;

        $this->assertSame(4200, $cost->publishedPrice($this->senior(['home_state' => 'OH']), $college));
        $this->assertSame(9800, $cost->publishedPrice($this->senior(['home_state' => 'TX']), $college));
    }

    /**
     * ⚠️ The vision's own sentence: "The tool will not apply for them — it
     * gives them the precise links and guides them through it step by step."
     * Every step must be addressed to the student, and no route may exist that
     * submits anything anywhere.
     */
    #[Test]
    public function nothing_applies_on_the_students_behalf(): void
    {
        $steps = (new CollegeApplication)->steps($this->senior(), College::factory()->create());

        foreach ($steps as $step) {
            $this->assertTrue($step['yours'], "A step is not the student's — {$step['title']}");
        }

        $submit = collect($steps)->firstWhere('title', 'Submit it');
        $this->assertStringContainsString('You press the button', $submit['detail']);

        /*
         | Asserting the right sentence is present does not stop a wrong one
         | being added beside it — the first version of this test passed
         | happily with "We can file this for you." prepended to that exact
         | step. So every line of the walkthrough is linted for an offer to
         | act. The negations the copy actually uses ("We will not file
         | anything on your behalf") do not match, because the word between
         | the verb and the offer is what distinguishes them.
         */
        foreach ($steps as $step) {
            foreach ([$step['title'], $step['detail']] as $copy) {
                $this->assertDoesNotMatchRegularExpression(
                    "/\b(we|we'll|the tool|this tool)\s+(can|will|could|are able to)\s+(file|submit|apply|send|complete|handle)/i",
                    $copy,
                    "The walkthrough offers to do it for them — {$copy}",
                );
            }
        }

        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        foreach ($routes as $uri) {
            $this->assertStringNotContainsString('colleges/{college:slug}/apply', $uri);
            $this->assertStringNotContainsString('colleges/{college:slug}/submit', $uri);
        }
    }

    /**
     * The transcript and the recommendations have to be asked for weeks
     * ahead. A walkthrough that lists them beside the deadline is a
     * walkthrough that makes students miss it.
     */
    #[Test]
    public function the_steps_are_dated_backwards_from_the_deadline(): void
    {
        $college = College::factory()->create(['deadline_month' => 1, 'deadline_day' => 15]);
        $application = new CollegeApplication;
        $asOf = CarbonImmutable::parse('2026-09-16');

        $steps = collect($application->steps($this->senior(), $college, $asOf))->keyBy('title');
        $deadline = $application->deadline($college, $asOf);

        $this->assertSame('2027-01-15', $deadline->toDateString());
        $this->assertTrue(CarbonImmutable::parse($steps['Ask for your transcript']['due_on'])->lessThan($deadline));
        $this->assertTrue(CarbonImmutable::parse($steps['Ask two people for a recommendation']['due_on'])->lessThan($deadline));
    }

    /**
     * A deadline that has already passed must roll to the next one. A senior
     * looking in February should see the date they are working toward.
     */
    #[Test]
    public function a_passed_deadline_rolls_to_the_next_year(): void
    {
        $college = College::factory()->create(['deadline_month' => 1, 'deadline_day' => 15]);

        $deadline = (new CollegeApplication)->deadline($college, CarbonImmutable::parse('2026-02-01'));

        $this->assertSame('2027-01-15', $deadline->toDateString());
    }

    /**
     * A college surfaces because of a programme it actually runs, matched to a
     * category the engine derived from what the student said. The reason is
     * shipped with the result — a student who asks "why is this here?" gets
     * the programme name rather than a shrug.
     */
    #[Test]
    public function a_school_appears_because_of_a_named_programme(): void
    {
        $this->enableCoach();
        $student = $this->senior();
        $category = $this->categoryFor($student, 'Healing and Restoration');

        $match = College::factory()->create(['name' => 'Riverbend College', 'slug' => 'riverbend']);
        $match->vocationalCategories()->attach($category, ['program_name' => 'Respiratory Therapy']);
        College::factory()->create(['name' => 'Unrelated Tech', 'slug' => 'unrelated']);

        $results = $this->actingAs($student)->get('/plan/colleges')->viewData('page')['props']['results'];

        $this->assertCount(1, $results);
        $this->assertSame('Riverbend College', $results[0]['name']);
        $this->assertSame(['Respiratory Therapy'], $results[0]['programs']);
    }

    /**
     * The portrait narrows the list; it does not own it. A student must always
     * be able to overrule it, because a seventeen-year-old changing their mind
     * about what they want is the product working, not a bug.
     */
    #[Test]
    public function the_student_can_overrule_their_own_portrait(): void
    {
        $this->enableCoach();
        $student = $this->senior();
        $this->categoryFor($student, 'Healing and Restoration');

        $other = VocationalCategory::create([
            'name' => 'Building and Making',
            'slug' => 'building-and-making',
            'description' => 'x',
            'sort_order' => 2,
        ]);
        $college = College::factory()->create(['name' => 'Foundry Tech', 'slug' => 'foundry']);
        $college->vocationalCategories()->attach($other, ['program_name' => 'Welding Technology']);

        $results = $this->actingAs($student)
            ->get('/plan/colleges?category=building-and-making')
            ->viewData('page')['props']['results'];

        $this->assertSame('Foundry Tech', $results[0]['name']);
    }

    /**
     * The one thing that can honestly be said about a whole list is what is
     * missing from its shape. It is never a score for the list.
     */
    #[Test]
    public function the_list_is_told_what_it_is_missing_and_never_scored(): void
    {
        $this->enableCoach();
        $student = $this->senior(['gpa' => 3.50]);

        $inRange = College::factory()->create(['gpa_25th' => 3.20, 'gpa_75th' => 3.80]);
        $student->collegeInterests()->attach($inRange);

        $balance = $this->actingAs($student)->get('/plan/colleges')->viewData('page')['props']['balance'];

        $this->assertContains(AdmissionStanding::Reach->listAdvice(), $balance);
        $this->assertContains(AdmissionStanding::Likely->listAdvice(), $balance);
        $this->assertNotContains(AdmissionStanding::Possible->listAdvice(), $balance);
    }

    /**
     * Every college page hands the student a question to take to somebody. The
     * vision is explicit that college choice "isn't meant to be self-serve,
     * it's meant to pull the family into the decision".
     */
    #[Test]
    public function every_college_hands_the_student_something_to_take_to_an_adult(): void
    {
        $this->enableCoach();
        $student = $this->senior();
        $college = College::factory()->create(['slug' => 'riverbend', 'name' => 'Riverbend College']);

        $props = $this->actingAs($student)->get('/plan/colleges/riverbend')->viewData('page')['props'];

        $this->assertNotEmpty($props['conversation']);
        $this->assertStringContainsString('Riverbend College', $props['conversation']);
        $this->assertStringNotContainsString(':college', $props['conversation']);
    }

    /**
     * The list is the student's own act, and reversible. Nobody else's id
     * appears in either route.
     */
    #[Test]
    public function a_student_puts_a_school_on_their_own_list_and_takes_it_off(): void
    {
        $this->enableCoach();
        $student = $this->senior();
        College::factory()->create(['slug' => 'riverbend']);

        $this->actingAs($student)->post('/plan/colleges/riverbend/list');
        $this->assertSame(1, $student->collegeInterests()->count());

        $this->actingAs($student)->post('/plan/colleges/riverbend/list');
        $this->assertSame(1, $student->collegeInterests()->count(), 'Adding twice duplicated the entry.');

        $this->actingAs($student)->delete('/plan/colleges/riverbend/list');
        $this->assertSame(0, $student->collegeInterests()->count());
    }

    /**
     * A GPA outside 0–4.0 would produce a standing that is simply wrong, and a
     * student will make a real decision on it.
     */
    #[Test]
    public function the_two_facts_we_ask_for_are_validated(): void
    {
        $this->enableCoach();
        $student = $this->senior();

        $this->actingAs($student)
            ->patch('/plan/colleges/inputs', ['gpa' => 7.4])
            ->assertSessionHasErrors('gpa');

        $this->actingAs($student)
            ->patch('/plan/colleges/inputs', ['household_income_band' => 'whatever'])
            ->assertSessionHasErrors('household_income_band');

        $this->actingAs($student)
            ->patch('/plan/colleges/inputs', ['gpa' => 3.1, 'household_income_band' => IncomeBand::UpTo30k->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(3.1, $student->fresh()->gpa);
    }

    /**
     * The explorer is not a sixth place. The vision names five, and 3.1 made
     * that list derive from the router — a sixth top-level tab would change
     * the shape the product promises without anybody deciding to.
     */
    #[Test]
    public function the_explorer_lives_inside_the_plan(): void
    {
        $this->assertTrue(Route::has('colleges'));
        $this->assertSame('plan/colleges', Route::getRoutes()->getByName('colleges')->uri());
        $this->assertSame(5, count(StudentPlace::cases()));
    }

    /**
     * A community college and a welding school sit in the same list as the
     * universities. Making a student leave the "college explorer" to find a
     * trade programme teaches them theirs is the lesser path.
     */
    #[Test]
    public function trade_and_community_schools_are_in_the_same_list(): void
    {
        $this->enableCoach();
        $student = $this->senior();

        College::factory()->create(['name' => 'State University']);
        College::factory()->openAdmission()->create(['name' => 'County Community', 'kind' => CollegeKind::Community]);
        College::factory()->openAdmission()->create(['name' => 'Northside Trades', 'kind' => CollegeKind::Technical]);

        $names = collect($this->actingAs($student)->get('/plan/colleges')->viewData('page')['props']['results'])
            ->pluck('name');

        $this->assertCount(3, $names);
        $this->assertContains('Northside Trades', $names);
    }

    /**
     * The importer maps the federal Scorecard's own column names, and its
     * `PrivacySuppressed` sentinel must become null rather than zero.
     */
    #[Test]
    public function the_importer_reads_the_federal_columns_and_suppresses_nothing_into_zero(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scorecard').'.csv';
        file_put_contents($path, implode("\n", [
            'INSTNM,CITY,STABBR,CONTROL,ICLEVEL,ADM_RATE,TUITIONFEE_IN,TUITIONFEE_OUT,NPT41_PUB,NPT45_PUB,APPLICATION_URL',
            'County Community,Dayton,OH,1,2,1.0,4200,9800,3100,PrivacySuppressed,https://example.edu/apply',
            'Nameless,,,,,,,,,,',
        ]));

        $this->artisan('colleges:import', ['path' => $path])->assertSuccessful();

        $college = College::where('name', 'County Community')->firstOrFail();

        $this->assertSame(CollegeControl::Public, $college->control);
        $this->assertSame(CollegeKind::Community, $college->kind);
        $this->assertSame(3100, $college->net_price_by_income[IncomeBand::UpTo30k->value]);
        $this->assertArrayNotHasKey(IncomeBand::Over110k->value, $college->net_price_by_income);
        $this->assertNull($college->gpa_25th);
        $this->assertSame(1, College::count(), 'The unusable row was imported anyway.');

        unlink($path);
    }

    /**
     * A category the engine actually scored for this student.
     */
    protected function categoryFor(User $student, string $name): VocationalCategory
    {
        $category = VocationalCategory::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'x',
            'sort_order' => 1,
        ]);

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'status' => 'completed',
            'mode' => 'written',
            'started_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'category_scores' => [['category' => $name, 'score' => 9]],
        ]);

        return $category;
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<string>
     */
    protected function keysIn(array $payload): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                $keys = array_merge($keys, $this->keysIn($value));
            }
        }

        return array_unique($keys);
    }
}
