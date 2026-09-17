<?php

namespace Tests\Feature;

use App\Enums\StudentPlace;
use App\Enums\VettingStatus;
use App\Enums\WorkKind;
use App\Models\Assessment;
use App\Models\FeatureFlag;
use App\Models\JobListing;
use App\Models\User;
use App\Models\VocationalCategory;
use App\Models\VocationalProfile;
use App\Support\JobApplicationGuide;
use App\Support\JobVetting;
use App\Support\StudentJobs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vetted work, and the one thing this layer must never do.
 *
 * Everything else in the product risks giving a student a bad sentence. This
 * layer risks sending a sixteen-year-old to a stranger's address. The gates
 * are therefore asserted against the query a student's page actually runs,
 * and every default is checked for failing closed rather than open.
 */
class VettedWorkTest extends TestCase
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

    protected function sixteenYearOld(): User
    {
        return User::factory()->create(['birthdate' => now()->subYears(16), 'grade_level' => 11]);
    }

    protected function adult(): User
    {
        return User::factory()->create(['birthdate' => now()->subYears(19)]);
    }

    /**
     * ⚠️ The invariant this whole layer exists for. An unexamined posting is
     * invisible, not shown with a caution — a warned-about listing is a
     * listing somebody clicks.
     */
    #[Test]
    public function an_unvetted_listing_reaches_nobody(): void
    {
        $student = $this->sixteenYearOld();

        JobListing::factory()->create(['minimum_age' => 16, 'vetting_status' => VettingStatus::Pending]);
        JobListing::factory()->create(['minimum_age' => 16, 'vetting_status' => VettingStatus::Rejected]);

        $this->assertCount(0, (new StudentJobs)->visibleTo($student));

        JobListing::factory()->openToTeens()->create();
        $this->assertCount(1, (new StudentJobs)->visibleTo($student));
    }

    /**
     * Pending is the column default. If that ever changes to passed, every
     * listing ingested overnight goes straight in front of minors.
     */
    #[Test]
    public function a_listing_is_unvetted_until_somebody_vets_it(): void
    {
        $listing = JobListing::create([
            'title' => 'Anything',
            'company_name' => 'Anyone',
            'source' => 'test',
            'source_id' => Str::uuid()->toString(),
        ]);

        $this->assertSame(VettingStatus::Pending, $listing->fresh()->vetting_status);
        $this->assertFalse(VettingStatus::Pending->isShowable());
        $this->assertFalse(VettingStatus::Rejected->isShowable());
        $this->assertTrue(VettingStatus::Passed->isShowable());
    }

    /**
     * ⚠️ A posting that never mentions age was not written with a minor in
     * mind. Silence is not permission, and the `whereNull` case must be
     * excluded rather than included.
     */
    #[Test]
    public function a_posting_that_says_nothing_about_age_is_not_shown_to_a_minor(): void
    {
        $listing = JobListing::factory()->create([
            'vetting_status' => VettingStatus::Passed,
            'minimum_age' => null,
        ]);

        $this->assertCount(0, (new StudentJobs)->visibleTo($this->sixteenYearOld()));

        // An adult may look at anything that passed vetting.
        $this->assertTrue((new StudentJobs)->visibleTo($this->adult())->contains('id', $listing->id));
    }

    /**
     * A sixteen-year-old must not be handed work that requires eighteen.
     */
    #[Test]
    public function nobody_is_shown_work_they_are_too_young_to_do(): void
    {
        $student = $this->sixteenYearOld();

        JobListing::factory()->openToTeens(18)->create(['title' => 'Night stocker']);
        $ok = JobListing::factory()->openToTeens(16)->create(['title' => 'Kennel assistant']);

        $visible = (new StudentJobs)->visibleTo($student);

        $this->assertCount(1, $visible);
        $this->assertSame($ok->id, $visible->first()->id);
    }

    /**
     * An unknown birthdate is treated as the youngest a high schooler could
     * be, matching AccessPolicy. Being wrong this way hides work from somebody
     * who could have done it; being wrong the other way sends a fourteen-year-
     * old to a night shift.
     */
    #[Test]
    public function an_unknown_age_fails_closed(): void
    {
        $unknown = User::factory()->create(['birthdate' => null, 'grade_level' => null]);

        JobListing::factory()->openToTeens(16)->create();

        $this->assertCount(0, (new StudentJobs)->visibleTo($unknown));

        JobListing::factory()->openToTeens(14)->create();
        $this->assertCount(1, (new StudentJobs)->visibleTo($unknown));
    }

    /**
     * The detail page and the list must agree. A rule re-implemented with an
     * `if` in a controller is a rule with two versions.
     */
    #[Test]
    public function a_listing_a_student_may_not_see_is_not_found_rather_than_forbidden(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();

        $hidden = JobListing::factory()->create(['vetting_status' => VettingStatus::Rejected, 'minimum_age' => 16]);
        $tooOld = JobListing::factory()->openToTeens(18)->create();

        $this->actingAs($student)->get("/plan/work/{$hidden->id}")->assertNotFound();
        $this->actingAs($student)->get("/plan/work/{$tooOld->id}")->assertNotFound();

        $ok = JobListing::factory()->openToTeens(16)->create();
        $this->actingAs($student)->get("/plan/work/{$ok->id}")->assertOk();
    }

    /**
     * The same shapes in Spanish and Portuguese. Bilingual postings are
     * ordinary in the districts this product is sold to, and a scam
     * translated is still a scam — a lint that reads only English protects
     * only the students who read only English.
     */
    #[Test]
    public function the_scam_shapes_are_rejected_in_the_other_languages_too(): void
    {
        $vetting = new JobVetting;

        $shapes = [
            'pay_to_work' => 'Se requiere una pequeña inversión para tu kit de inicio.',
            'money_mule' => 'Vas a recibir paquetes y reenviar los envíos por nosotros.',
            'data_harvest' => 'Envía tu número de seguro social para aplicar.',
            'no_real_employer' => 'Contratación inmediata sin entrevista. Escríbenos para aplicar.',
            'isolation' => 'Trabajo discreto, no le digas a nadie.',
        ];

        $portuguese = [
            'pay_to_work' => 'Pague pelo treinamento e receba o kit inicial.',
            'money_mule' => 'Você vai receber pacotes e reenviar para nós.',
            'data_harvest' => 'Envie seu CPF para se candidatar hoje.',
            'no_real_employer' => 'Contratação imediata sem entrevista. Entrevista pelo WhatsApp.',
            'isolation' => 'Trabalho discreto, não conte a ninguém.',
        ];

        foreach ([...array_map(null, array_keys($shapes), $shapes), ...array_map(null, array_keys($portuguese), $portuguese)] as [$rule, $copy]) {
            $listing = JobListing::factory()->create(['description_plain' => $copy, 'description' => $copy]);

            $rules = array_column(JobVetting::blocking($vetting->inspect($listing)), 'rule');

            $this->assertContains($rule, $rules, "This posting was not rejected — {$copy}");
        }
    }

    /**
     * Accents are folded before matching. A scraped posting has been through
     * an HTML pipeline and a copy-paste before it reaches us, and "reenvío"
     * arriving as "reenvio" is not a reason to publish a reshipping scam.
     */
    #[Test]
    public function a_missing_accent_does_not_get_a_posting_published(): void
    {
        $vetting = new JobVetting;

        /*
         | The accented spelling is the one the phrase lists do *not* contain,
         | so it is the one that proves the folding. Both have to be rejected:
         | a posting is not made safe by a diacritic.
         */
        foreach ([
            'Envía tu número de seguro social para aplicar.',
            'Envia tu numero de seguro social para aplicar.',
        ] as $copy) {
            $listing = JobListing::factory()->create(['description_plain' => $copy, 'description' => $copy]);

            $this->assertContains(
                'data_harvest',
                array_column(JobVetting::blocking($vetting->inspect($listing)), 'rule'),
                "Not caught: {$copy}",
            );
        }
    }

    /**
     * And an ordinary Spanish posting still passes. "Transferencia bancaria"
     * is how payroll itself is described in Spanish, where "wire transfer" is
     * not how it is described in English — so it is deliberately absent from
     * the money-movement list. A lint that rejects every job mentioning direct
     * deposit has not gained Spanish, it has only stopped serving Spanish
     * speakers.
     */
    #[Test]
    public function an_ordinary_posting_in_spanish_is_not_rejected(): void
    {
        $copy = 'Asistente de cocina, 16 horas por semana. Pago quincenal por '
            .'transferencia bancaria. Se requiere permiso de trabajo escolar.';

        $listing = JobListing::factory()->create([
            'description_plain' => $copy,
            'description' => $copy,
            'company_name' => 'Panadería Solano',
            'company_url' => 'https://example.com',
        ]);

        $this->assertSame([], JobVetting::blocking((new JobVetting)->inspect($listing)));
    }

    /**
     * The scam shapes that actually target teenagers: advance fees, money
     * movement, identity harvesting, chat-app-only interviews.
     */
    #[Test]
    public function the_documented_scam_shapes_are_rejected(): void
    {
        $vetting = new JobVetting;

        $shapes = [
            'pay_to_work' => 'Small investment required for your starter kit.',
            'money_mule' => 'You will receive packages and reship them for us.',
            'data_harvest' => 'Send your social security number to apply.',
            'no_real_employer' => 'Hiring immediately no interview. Text us to apply.',
            'isolation' => 'Discreet work, no questions asked.',
        ];

        foreach ($shapes as $rule => $copy) {
            $listing = JobListing::factory()->create(['description_plain' => $copy, 'description' => $copy]);

            $findings = $vetting->inspect($listing);
            $rules = array_column(JobVetting::blocking($findings), 'rule');

            $this->assertContains($rule, $rules, "This posting was not rejected — {$copy}");

            $vetting->vet($listing);
            $this->assertSame(VettingStatus::Rejected, $listing->fresh()->vetting_status);
        }
    }

    /**
     * A posting nobody can check is blocked rather than warned about. This is
     * a fact about the record, so no phrasing can be written around it.
     */
    #[Test]
    public function a_posting_nobody_can_verify_is_blocked(): void
    {
        $vetting = new JobVetting;

        /*
         | Filtered to the blocking findings, not to all of them. The first
         | version of this assertion checked only that the rule *fired*, which
         | stayed true when the rule was downgraded to a warning and the
         | anonymous posting went back on the site.
         */
        $anonymous = JobListing::factory()->create(['company_name' => '']);
        $this->assertContains(
            'anonymous_employer',
            array_column(JobVetting::blocking($vetting->inspect($anonymous)), 'rule'),
        );

        $unreachable = JobListing::factory()->create(['company_url' => null, 'source_url' => null]);
        $this->assertContains('unreachable_employer', array_column(JobVetting::blocking($vetting->inspect($unreachable)), 'rule'));
    }

    /**
     * Hype is a warning, never a rejection. A real job described badly is
     * still a real job, and the asymmetry only runs one way — toward blocking
     * what is dangerous, not toward blocking what is tacky.
     */
    #[Test]
    public function a_badly_written_posting_is_not_a_rejected_one(): void
    {
        $vetting = new JobVetting;
        $listing = JobListing::factory()->create([
            'description_plain' => 'Unlimited earning potential! Be your own boss.',
            'description' => 'Unlimited earning potential! Be your own boss.',
        ]);

        $findings = $vetting->inspect($listing);

        $this->assertNotEmpty(JobVetting::warnings($findings));
        $this->assertSame([], JobVetting::blocking($findings));

        $vetting->vet($listing);
        $this->assertSame(VettingStatus::Passed, $listing->fresh()->vetting_status);
    }

    /**
     * A clean listing passes. A lint that rejects everything protects nobody
     * because it gets turned off.
     */
    #[Test]
    public function an_ordinary_posting_passes(): void
    {
        $listing = JobListing::factory()->create();

        $this->assertSame([], JobVetting::blocking((new JobVetting)->inspect($listing)));
    }

    /**
     * The command is how a rule added on Tuesday reaches a listing ingested on
     * Monday.
     */
    #[Test]
    public function the_command_vets_the_unexamined_and_can_revisit_everything(): void
    {
        JobListing::factory()->create();
        JobListing::factory()->scam()->create();
        $alreadyPassed = JobListing::factory()->scam()->create(['vetting_status' => VettingStatus::Passed]);

        $this->artisan('jobs:vet')->assertSuccessful();

        $this->assertSame(VettingStatus::Passed, JobListing::where('title', 'Veterinary Assistant')->first()->vetting_status);
        $this->assertSame(VettingStatus::Passed, $alreadyPassed->fresh()->vetting_status, 'A pass-only run touched a listing it should have left alone.');

        $this->artisan('jobs:vet', ['--all' => true])->assertSuccessful();
        $this->assertSame(VettingStatus::Rejected, $alreadyPassed->fresh()->vetting_status);
    }

    /**
     * ⚠️ The same boundary the college walkthrough holds. Every step is the
     * student's, and no copy offers to do it for them — asserting the right
     * sentence is present never excludes a wrong one added beside it.
     */
    #[Test]
    public function nothing_applies_to_an_employer_on_the_students_behalf(): void
    {
        $steps = (new JobApplicationGuide)->steps($this->sixteenYearOld(), JobListing::factory()->create());

        foreach ($steps as $step) {
            $this->assertTrue($step['yours']);

            foreach ([$step['title'], $step['detail']] as $copy) {
                $this->assertDoesNotMatchRegularExpression(
                    "/\\b(we|we'll|the tool|this tool)\\s+(can|will|could|are able to)\\s+(apply|send|submit|contact|email|message)/i",
                    $copy,
                    "The guide offers to do it for them — {$copy}",
                );
            }
        }

        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        foreach ($routes as $uri) {
            $this->assertStringNotContainsString('work/{jobListing}/apply', $uri);
        }
    }

    /**
     * Two things specific to putting a minor in front of an employer. A
     * student who finds out about the work permit on their first day has
     * already lost the job, and an interview is a meeting with a stranger at
     * an address.
     */
    #[Test]
    public function a_minor_is_told_about_the_permit_and_told_to_tell_somebody(): void
    {
        $guide = new JobApplicationGuide;
        $listing = JobListing::factory()->create();

        $minorSteps = collect($guide->steps($this->sixteenYearOld(), $listing));
        $this->assertNotNull($minorSteps->firstWhere('title', 'Get your work permit first'));
        $this->assertNotNull($minorSteps->firstWhere('title', 'Tell somebody where the interview is'));

        $adultSteps = collect($guide->steps($this->adult(), $listing));
        $this->assertNull($adultSteps->firstWhere('title', 'Get your work permit first'));
        $this->assertNotNull(
            $adultSteps->firstWhere('title', 'Tell somebody where the interview is'),
            'Telling somebody where you are going is not a thing you stop doing at eighteen.',
        );
    }

    /**
     * The questions whose absence is the actual risk: hours, pay, supervision,
     * and who to tell.
     */
    #[Test]
    public function a_minor_is_given_the_questions_an_adult_would_know_to_ask(): void
    {
        $guide = new JobApplicationGuide;
        $listing = JobListing::factory()->create(['supervised' => false]);

        $questions = implode(' ', $guide->questionsToAsk($this->sixteenYearOld(), $listing));

        $this->assertStringContainsString('under 18', $questions);
        $this->assertStringContainsString('not comfortable with', $questions);
        $this->assertStringContainsString('on my own', $questions);
    }

    /**
     * Our reasoning about a posting is not shipped. A page of caveats teaches
     * students to read past caveats, and a rejected listing never reaches the
     * payload at all.
     */
    #[Test]
    public function the_vetting_findings_never_reach_the_student(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();
        JobListing::factory()->openToTeens()->create([
            'description_plain' => 'Unlimited earning potential.',
            'description' => 'Unlimited earning potential.',
        ]);

        $props = $this->actingAs($student)->get('/plan/work')->viewData('page')['props'];
        $serialised = json_encode($props);

        foreach (['vetting_findings', 'vetting_status', 'severity', 'blocking'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialised);
        }
    }

    /**
     * Apprenticeships sit in the same list as jobs. Putting them in their own
     * tab is an argument about which one is the real option.
     */
    #[Test]
    public function apprenticeships_are_in_the_same_list_as_jobs(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();

        JobListing::factory()->openToTeens()->create();
        JobListing::factory()->openToTeens()->apprenticeship()->create();

        $results = $this->actingAs($student)->get('/plan/work')->viewData('page')['props']['results'];

        $this->assertCount(2, $results);
        $this->assertContains('Apprenticeship', array_column($results, 'kind'));
        $this->assertStringContainsString('not the lesser path', WorkKind::Apprenticeship->description());
    }

    /**
     * Pay that is not stated says so. A blank is how a student finds out on
     * day one that it is unpaid.
     */
    #[Test]
    public function work_with_no_stated_pay_says_so(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();
        JobListing::factory()->openToTeens()->create(['salary_min' => null, 'salary_max' => null]);

        $results = $this->actingAs($student)->get('/plan/work')->viewData('page')['props']['results'];

        $this->assertStringContainsString('not said what it pays', $results[0]['pay']);
    }

    /**
     * Why a listing is in front of this student is the same
     * portrait → category → listing join the college explorer uses.
     */
    #[Test]
    public function the_portrait_narrows_the_list_and_the_student_can_overrule_it(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();

        $category = VocationalCategory::create([
            'name' => 'Healing and Restoration',
            'slug' => 'healing-and-restoration',
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
            'category_scores' => [['category' => 'Healing and Restoration', 'score' => 9]],
        ]);

        $matched = JobListing::factory()->openToTeens()->create(['title' => 'Kennel assistant']);
        $matched->vocationalCategories()->attach($category, ['id' => Str::uuid()->toString()]);
        JobListing::factory()->openToTeens()->create(['title' => 'Unrelated warehouse work']);

        $visible = (new StudentJobs)->visibleTo($student);
        $this->assertCount(1, $visible);
        $this->assertSame('Kennel assistant', $visible->first()->title);
    }

    /**
     * Work lives inside the plan, like the college explorer. The vision names
     * five places and 3.1 made the navigation derive from the router.
     */
    #[Test]
    public function work_lives_inside_the_plan(): void
    {
        $this->assertSame('plan/work', Route::getRoutes()->getByName('work')->uri());
        $this->assertSame(5, count(StudentPlace::cases()));
    }

    /**
     * The schedule is load-bearing in a way it usually is not.
     *
     * Because an unvetted listing is invisible by design, a vetting pass that
     * never runs does not degrade the student's page — it empties it, silently
     * and permanently, while ingestion keeps filling the table. The failure
     * looks like "there are no jobs this week" rather than like a broken
     * feature, which is why it is asserted rather than trusted.
     */
    #[Test]
    public function the_vetting_pass_is_actually_scheduled(): void
    {
        /*
         | Matched per event rather than against one joined string. A
         | `str_contains($all, 'jobs:vet')` is satisfied by `jobs:vet --all`
         | alone, so deleting the hourly pass entirely left the first version
         | of this test perfectly green — the same substring trap that once
         | hid a dead invitation URL in 3.7.
         */
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => trim(str_replace("'", '', Str::after($event->command ?? '', 'artisan'))));

        $this->assertTrue(
            $commands->contains(fn (string $command) => $command === 'jobs:vet'),
            'Nothing vets newly ingested listings, so the student list is permanently empty.',
        );
        $this->assertTrue(
            $commands->contains(fn (string $command) => $command === 'jobs:vet --all'),
            'Nothing re-vets against updated rules.',
        );
    }

    /**
     * ⚠️ The legacy `/jobs` board shows every classified listing with no
     * vetting and no age requirement anywhere in its query. It predates there
     * being minors in this product. A student who reaches it is sent to the
     * one that has the rules, rather than the rules being re-implemented in a
     * second place where they would drift.
     */
    #[Test]
    public function the_legacy_adult_board_refuses_minors(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'job_discovery'],
            ['name' => 'Job Discovery', 'is_enabled' => true],
        );
        Cache::forget('feature_flag:job_discovery');

        JobListing::factory()->create(['vetting_status' => VettingStatus::Pending]);

        $this->actingAs($this->sixteenYearOld())->get('/jobs')->assertRedirect(route('work'));

        $unknownAge = User::factory()->create(['birthdate' => null, 'grade_level' => null]);
        $this->actingAs($unknownAge)->get('/jobs')->assertRedirect(route('work'));

        $this->actingAs($this->adult())->get('/jobs')->assertOk();
    }

    /**
     * Every college and every employer hands the student a question for an
     * adult. Work is where the terms matter most and where a sixteen-year-old
     * has least idea what is unusual.
     */
    #[Test]
    public function every_posting_hands_the_student_something_to_take_to_an_adult(): void
    {
        $this->enableCoach();
        $student = $this->sixteenYearOld();
        $listing = JobListing::factory()->openToTeens()->create(['company_name' => 'Bell Road Animal Clinic']);

        $props = $this->actingAs($student)->get("/plan/work/{$listing->id}")->viewData('page')['props'];

        $this->assertNotEmpty($props['conversation']);
        $this->assertStringNotContainsString(':company', $props['conversation']);
    }
}
