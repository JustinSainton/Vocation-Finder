<?php

namespace Tests\Feature;

use App\Ai\Agents\SyllabusParser;
use App\Enums\CampusResourceKind;
use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use App\Enums\StudentPlace;
use App\Jobs\ParseSyllabusJob;
use App\Models\College;
use App\Models\CollegeResource;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\Milestone;
use App\Models\Syllabus;
use App\Models\User;
use App\Support\CampusGuide;
use App\Support\IcsFeed;
use App\Support\SyllabusImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Being in college — the last layer the vision names, and the one with the
 * sharpest failure mode after vetted work.
 *
 * A wrong deadline here is worse than no deadline: a student who trusts this
 * calendar and misses a paper has been harmed by the thing that was supposed
 * to help them. So every assertion below is about what the product refuses to
 * do — invent a date, invent a campus office, notify somebody, or hand the
 * coursework to a second list that competes with the plan.
 */
class InCollegeTest extends TestCase
{
    use RefreshDatabase;

    protected const SYLLABUS = <<<'TEXT'
    ENG 101 — Composition I
    Fall term. Prof. Alvarez. Office hours Tuesdays.

    Reading response 1 — due Friday, Sept 12
    Midterm essay — due Oct 14
    Group presentation — Nov 3
    Final portfolio — due 12/5
    TEXT;

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    protected function student(): User
    {
        return User::factory()->create(['birthdate' => now()->subYears(19)]);
    }

    protected function syllabusFor(User $student, array $attributes = []): Syllabus
    {
        return Syllabus::factory()->create(array_merge([
            'user_id' => $student->id,
            'source_text' => self::SYLLABUS,
            'created_at' => '2026-09-01 09:00:00',
        ], $attributes));
    }

    /*
     |--------------------------------------------------------------------
     | Provenance: the model points, the code computes
     |--------------------------------------------------------------------
     */

    /**
     * The ordinary case. A title and a date both printed in the document
     * become one dated milestone.
     */
    #[Test]
    public function an_assignment_printed_in_the_syllabus_becomes_a_dated_milestone(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Midterm essay', 'due_text' => 'Oct 14'],
        ]);

        $this->assertCount(1, $kept);
        $this->assertSame('Midterm essay', $kept->first()->title);
        $this->assertSame('2026-10-14', $kept->first()->due_on->toDateString());
    }

    /**
     * The whole point of the layer. A title the model produced that is not in
     * the document is a hallucinated deadline, and it is dropped by substring
     * check rather than by anybody's judgement — the same discipline as
     * verbatim signal provenance in Layer 4.
     */
    #[Test]
    public function an_assignment_that_is_not_in_the_syllabus_is_discarded(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Research proposal', 'due_text' => 'Oct 14'],
        ]);

        $this->assertCount(0, $kept);
        $this->assertSame(0, Milestone::query()->count());
    }

    /**
     * And the date has to be in there too. A real assignment with a date
     * nobody printed is the more dangerous half of the same failure, because
     * the title looks right when the student skims it.
     */
    #[Test]
    public function a_date_that_is_not_in_the_syllabus_is_discarded(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Midterm essay', 'due_text' => 'Oct 21'],
        ]);

        $this->assertCount(0, $kept);
        $this->assertSame(0, Milestone::query()->count());
    }

    /**
     * Nothing is dropped silently. A syllabus format we read badly has to be
     * visible to the student it failed, so they know to check the document
     * rather than trusting a calendar that quietly lost a midterm.
     */
    #[Test]
    public function everything_discarded_is_recorded_with_its_reason(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Research proposal', 'due_text' => 'Oct 14'],
            ['title' => 'Midterm essay', 'due_text' => 'Oct 21'],
            ['title' => 'Midterm essay', 'due_text' => ''],
        ]);

        $discarded = $syllabus->fresh()->discarded;

        $this->assertCount(3, $discarded);
        $this->assertSame(
            ['title_not_in_syllabus', 'date_not_in_syllabus', 'no_date'],
            array_column($discarded, 'reason'),
        );
    }

    /**
     * The model is never asked for a calendar date, only for the span the
     * syllabus printed. A schema field the model fills with an ISO date is a
     * plausible-looking number nothing can check, so the schema is asserted
     * to have exactly two fields and neither is a date.
     */
    #[Test]
    public function the_parser_is_never_asked_for_a_calendar_date(): void
    {
        $serialized = (new ObjectSchema((new SyllabusParser('text'))->schema(new JsonSchemaTypeFactory)))->toArray();
        $fields = array_keys($serialized['properties']['assignments']['items']['properties']);

        sort($fields);

        $this->assertSame(['due_text', 'title'], $fields);
    }

    /**
     * A syllabus almost never prints a year. A bare month and day belongs to
     * the term it was handed out in.
     */
    #[Test]
    public function a_bare_month_and_day_is_read_into_the_terms_year(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student, [
            'enrollment_id' => Enrollment::factory()->create([
                'user_id' => $student->id,
                'started_on' => '2026-08-24',
            ])->id,
        ]);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Reading response 1', 'due_text' => 'Friday, Sept 12'],
        ]);

        $this->assertSame('2026-09-12', $kept->first()->due_on->toDateString());
    }

    /**
     * And a spring date on an autumn syllabus is next year's, which is the
     * case that would otherwise put a February final four months in the past.
     */
    #[Test]
    public function a_date_before_the_term_began_rolls_into_the_following_year(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student, [
            'source_text' => "HIST 210\nFinal exam — Feb 3",
            'enrollment_id' => Enrollment::factory()->create([
                'user_id' => $student->id,
                'started_on' => '2026-08-24',
            ])->id,
        ]);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Final exam', 'due_text' => 'Feb 3'],
        ]);

        $this->assertSame('2027-02-03', $kept->first()->due_on->toDateString());
    }

    /**
     * A span we cannot read is dropped, never guessed at. "Week 6" is a real
     * thing syllabi print, and turning it into a date requires a term calendar
     * we do not have.
     */
    #[Test]
    public function a_span_with_no_readable_date_is_discarded_rather_than_guessed(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student, [
            'source_text' => "ENG 101\nMidterm essay — due Week 6",
        ]);

        $kept = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Midterm essay', 'due_text' => 'Week 6'],
        ]);

        $this->assertCount(0, $kept);
        $this->assertSame('date_not_understood', $syllabus->fresh()->discarded[0]['reason']);
    }

    /**
     * Re-reading a syllabus must not duplicate a student's coursework and must
     * never undo what they have already finished.
     */
    #[Test]
    public function reparsing_a_syllabus_neither_duplicates_nor_resets_progress(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);
        $importer = new SyllabusImporter;

        $first = $importer->store($syllabus, [['title' => 'Midterm essay', 'due_text' => 'Oct 14']]);
        $first->first()->update(['status' => MilestoneStatus::Done]);

        $importer->store($syllabus, [['title' => 'Midterm essay', 'due_text' => 'Oct 14']]);

        $this->assertSame(1, Milestone::query()->count());
        $this->assertSame(MilestoneStatus::Done, Milestone::query()->first()->status);
    }

    /**
     * Coursework is a milestone in the plan the student already reads, not a
     * second dated list beside it. A parallel assignment table would be a
     * second plan competing with the first, and the student would have to pick
     * which one to believe.
     */
    #[Test]
    public function coursework_lands_in_the_plan_rather_than_a_list_of_its_own(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        $milestone = (new SyllabusImporter)->store($syllabus, [
            ['title' => 'Final portfolio', 'due_text' => '12/5'],
        ])->first();

        $this->assertSame(MilestoneKind::Academic, $milestone->kind);
        $this->assertSame($syllabus->id, $milestone->syllabus_id);
        $this->assertSame($student->id, $milestone->user_id);
        $this->assertStringContainsString('ENG 101', (string) $milestone->why);
    }

    /*
     |--------------------------------------------------------------------
     | The calendar feed
     |--------------------------------------------------------------------
     */

    /**
     * The feed is the whole plan, not only the coursework. A scholarship
     * deadline and a problem set compete for the same Thursday evening, and a
     * student can only see that if both are in one place.
     */
    #[Test]
    public function the_feed_carries_every_dated_milestone_not_only_coursework(): void
    {
        $student = $this->student();
        $syllabus = $this->syllabusFor($student);

        (new SyllabusImporter)->store($syllabus, [['title' => 'Midterm essay', 'due_text' => 'Oct 14']]);

        Milestone::create([
            'user_id' => $student->id,
            'kind' => MilestoneKind::Money,
            'title' => 'Renew the FAFSA',
            'due_on' => '2026-10-01',
        ]);

        $body = $this->get(route('calendar.feed', ['token' => (new IcsFeed)->tokenFor($student)]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('SUMMARY:Midterm essay', $body);
        $this->assertStringContainsString('SUMMARY:Renew the FAFSA', $body);
    }

    /**
     * The calendar mirrors, it does not interrupt. A tool that puts itself on
     * somebody's lock screen at 8am has decided on their behalf that it gets
     * to interrupt them — the same refusal as the nudge seam defaulting to
     * silence.
     */
    #[Test]
    public function the_feed_never_sets_an_alarm(): void
    {
        $student = $this->student();

        Milestone::create([
            'user_id' => $student->id,
            'kind' => MilestoneKind::Test,
            'title' => 'Sit the ACT',
            'due_on' => '2026-10-24',
        ]);

        $body = $this->get(route('calendar.feed', ['token' => (new IcsFeed)->tokenFor($student)]))->getContent();

        $this->assertStringNotContainsString('VALARM', $body);
        $this->assertStringNotContainsString('TRIGGER', $body);
    }

    /**
     * The token is the entire authorisation, so a wrong one is a 404 and
     * reveals nothing about whether a student exists behind it.
     */
    #[Test]
    public function an_unknown_calendar_token_is_not_a_calendar(): void
    {
        /*
         | The student and their milestone exist, and the token does not. The
         | first version of this test created neither, so a controller that
         | fell back to "whichever student is first" passed it — an empty
         | database cannot tell you that a lookup is scoped.
         */
        $student = $this->student();
        (new IcsFeed)->tokenFor($student);

        Milestone::create([
            'user_id' => $student->id,
            'kind' => MilestoneKind::Test,
            'title' => 'Sit the ACT',
            'due_on' => '2026-10-24',
        ]);

        $this->get(route('calendar.feed', ['token' => str_repeat('a', 64)]))->assertNotFound();
    }

    /**
     * A subscribable URL lives in other people's devices. A student who has
     * shared a screen needs to be able to end it without asking us.
     */
    #[Test]
    public function rotating_the_token_breaks_the_old_link(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $feed = new IcsFeed;
        $old = $feed->tokenFor($student);

        $this->actingAs($student)->post(route('calendar.rotate'))->assertRedirect();

        $this->get(route('calendar.feed', ['token' => $old]))->assertNotFound();
        $this->get(route('calendar.feed', ['token' => $student->fresh()->calendar_token]))->assertOk();
    }

    /**
     * Commas and semicolons are field separators in an ICS file. A milestone
     * titled with one and not escaped produces a calendar entry that is either
     * truncated or refused, silently, on the student's phone.
     */
    #[Test]
    public function text_that_would_break_the_file_is_escaped(): void
    {
        $student = $this->student();

        Milestone::create([
            'user_id' => $student->id,
            'kind' => MilestoneKind::Application,
            'title' => 'Essay draft, second version; final',
            'due_on' => '2026-10-24',
        ]);

        $body = $this->get(route('calendar.feed', ['token' => (new IcsFeed)->tokenFor($student)]))->getContent();

        $this->assertStringContainsString('SUMMARY:Essay draft\\, second version\; final', $body);
    }

    /**
     * Nothing interpretive crosses into the calendar. A calendar entry is read
     * by whoever glances at a shared screen, and the vocational reading of
     * somebody's life is not a thing to leak onto a family iPad.
     */
    #[Test]
    public function no_interpretation_reaches_the_calendar(): void
    {
        $student = $this->student();

        Milestone::create([
            'user_id' => $student->id,
            'kind' => MilestoneKind::Build,
            'title' => 'Finish the portfolio site',
            'due_on' => '2026-11-02',
        ]);

        $body = $this->get(route('calendar.feed', ['token' => (new IcsFeed)->tokenFor($student)]))->getContent();

        $properties = collect(explode("\r\n", $body))
            ->filter(fn (string $line) => $line !== '' && ! str_starts_with($line, ' '))
            ->map(fn (string $line) => strtok($line, ':;'))
            ->unique()
            ->values()
            ->all();

        $this->assertSame([
            'BEGIN', 'VERSION', 'PRODID', 'CALSCALE', 'METHOD', 'X-WR-CALNAME',
            'UID', 'DTSTAMP', 'DTSTART', 'DTEND', 'SUMMARY', 'DESCRIPTION', 'TRANSP', 'END',
        ], $properties);
    }

    /*
     |--------------------------------------------------------------------
     | The surface
     |--------------------------------------------------------------------
     */

    /**
     * Still five places. The in-college layer is a section of the plan, like
     * the college explorer and vetted work before it.
     */
    #[Test]
    public function being_in_college_is_a_section_of_the_plan_not_a_sixth_place(): void
    {
        $this->assertSame('/plan/college-life', parse_url(route('college-life'), PHP_URL_PATH));
        $this->assertCount(5, StudentPlace::cases());
    }

    /**
     * A student at a college nobody has imported is exactly the student this
     * layer exists for, so the name is required and our own reference row is
     * not.
     */
    #[Test]
    public function a_student_may_enrol_at_a_college_we_have_never_heard_of(): void
    {
        $this->enableCoach();
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('college-life.enrollment.store'), ['college_name' => 'Sinclair Community College'])
            ->assertRedirect();

        $enrollment = $student->enrollments()->sole();

        $this->assertSame('Sinclair Community College', $enrollment->college_name);
        $this->assertNull($enrollment->college_id);
    }

    /**
     * The text is stored before anything is parsed out of it: provenance can
     * only be checked against a document we kept, and a provider timeout must
     * not lose the student's paste.
     */
    #[Test]
    public function uploading_a_syllabus_stores_the_text_and_queues_the_parse(): void
    {
        Bus::fake();
        $this->enableCoach();
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('college-life.syllabus.store'), [
                'course_code' => 'ENG 101',
                'source_text' => self::SYLLABUS,
            ])
            ->assertRedirect();

        $this->assertSame(self::SYLLABUS, $student->syllabi()->sole()->source_text);
        Bus::assertDispatched(ParseSyllabusJob::class);
    }

    /**
     * The campus baseline is true everywhere and needs no reference data. A
     * student whose college we have never imported still gets the accurate
     * general answer rather than an empty page.
     */
    #[Test]
    public function the_campus_baseline_needs_no_reference_data(): void
    {
        $resources = (new CampusGuide)->resources(null);

        $this->assertCount(count(CampusResourceKind::cases()), $resources);

        foreach ($resources as $resource) {
            $this->assertNotSame('', $resource['description']);
            $this->assertNotSame('', $resource['opener']);
            $this->assertSame([], $resource['links'], 'A campus we have no data for must not be given invented links.');
        }
    }

    /**
     * Institution-specific links are an upgrade layered on imported data, and
     * they belong to the college that was imported — never to the next one.
     */
    #[Test]
    public function institution_links_appear_only_for_the_college_they_belong_to(): void
    {
        $college = College::factory()->create();
        $other = College::factory()->create();

        CollegeResource::create([
            'college_id' => $college->id,
            'kind' => CampusResourceKind::FoodSecurity,
            'name' => 'Bearcat Pantry',
            'url' => 'https://example.edu/pantry',
        ]);

        $guide = new CampusGuide;
        $pantry = fn (array $resources) => collect($resources)->firstWhere('kind', 'food_security')['links'];

        $this->assertSame([['name' => 'Bearcat Pantry', 'url' => 'https://example.edu/pantry']], $pantry($guide->resources($college)));
        $this->assertSame([], $pantry($guide->resources($other)));
    }

    /**
     * Ending an enrollment is the student's own act, and there is no route by
     * which it is anybody else's.
     */
    #[Test]
    public function one_student_cannot_end_another_students_enrollment(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $enrollment = Enrollment::factory()->create(['user_id' => $this->student()->id]);

        $this->actingAs($student)
            ->delete(route('college-life.enrollment.end', $enrollment))
            ->assertNotFound();

        $this->assertNull($enrollment->fresh()->ended_at);
    }

    /**
     * The page shows the student's own coursework and the campus baseline, and
     * nothing that scores them.
     */
    #[Test]
    public function the_page_shows_coursework_and_campus_without_scoring_anybody(): void
    {
        $this->enableCoach();
        $student = $this->student();
        Enrollment::factory()->create(['user_id' => $student->id, 'college_name' => 'Ohio State']);
        $syllabus = $this->syllabusFor($student);
        (new SyllabusImporter)->store($syllabus, [['title' => 'Midterm essay', 'due_text' => 'Oct 14']]);

        $response = $this->actingAs($student)->get(route('college-life'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('Ohio State', $props['enrollment']['college_name']);
        $this->assertSame('Midterm essay', $props['coursework'][0]['title']);
        $this->assertStringContainsString('/calendar/', $props['calendar_url']);

        $encoded = json_encode($props);
        foreach (['readiness', 'confidence', 'score', 'percent'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $encoded);
        }
    }
}
