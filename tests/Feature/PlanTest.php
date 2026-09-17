<?php

namespace Tests\Feature;

use App\Ai\Tools\SetMilestoneTool;
use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use App\Models\FeatureFlag;
use App\Models\Milestone;
use App\Models\User;
use App\Support\ActionQueue;
use App\Support\PlanSections;
use App\Support\StudentPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request as ToolRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 3.2 — the plan.
 *
 * The plan is the product's widest view, and the wide view is where a tool
 * like this usually breaks its own promise: it becomes a list of twenty
 * things, or it starts scoring the student. Both are guarded here against the
 * payload rather than the markup, because the payload is what a second surface
 * would inherit.
 */
class PlanTest extends TestCase
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

    protected function junior(): User
    {
        return User::factory()->create(['birthdate' => now()->subYears(19), 'grade_level' => 11]);
    }

    /**
     * The vision asks for four years at a glance. Nobody types those years in
     * — the school calendar and a grade level already contain them, and a
     * value a student has to enter is a value that is wrong every September.
     */
    #[Test]
    public function the_sections_are_computed_from_the_calendar_and_the_grade(): void
    {
        $user = User::factory()->create(['grade_level' => 11]);
        $asOf = CarbonImmutable::parse('2026-03-10');

        $labels = array_column((new PlanSections)->for($user, $asOf), 'label');

        $this->assertSame([
            'Junior year',
            'Summer after junior year',
            'Senior year',
            'After high school',
        ], $labels);
    }

    /**
     * A junior in March is still a junior, and their year began last August.
     * If the school year rolled over on 1 January, every section in every
     * plan would slide six months halfway through the year.
     */
    #[Test]
    public function the_school_year_turns_over_in_august_not_january(): void
    {
        $user = User::factory()->create(['grade_level' => 11]);

        foreach (['2026-03-10' => '2025-08-01', '2026-09-10' => '2026-08-01'] as $date => $opens) {
            $sections = (new PlanSections)->for($user, CarbonImmutable::parse($date));
            $junior = collect($sections)->firstWhere('key', 'grade-11');

            $this->assertSame($opens, $junior['starts_on'], "Wrong school year on {$date}.");
            $this->assertTrue($junior['is_now'], "Junior year should be current on {$date}.");
        }
    }

    /**
     * Where a student is headed is not decided by us. Four named years of a
     * college nobody has chosen is identity foreclosure with a calendar
     * attached.
     */
    #[Test]
    public function the_plan_stops_at_the_end_of_school_and_leaves_the_rest_open(): void
    {
        $sections = (new PlanSections)->for(
            User::factory()->create(['grade_level' => 12]),
            CarbonImmutable::parse('2026-03-10'),
        );

        $last = end($sections);

        $this->assertSame('After high school', $last['label']);
        $this->assertNull($last['ends_on'], 'The plan put an end date on a life it does not know.');
    }

    /**
     * The whole point of "milestones live inside the plan rather than in a
     * separate view".
     */
    #[Test]
    public function a_milestone_lands_in_the_section_its_date_falls_in(): void
    {
        $user = User::factory()->create(['grade_level' => 11]);

        Milestone::factory()->for($user)->create([
            'title' => 'Sit the SAT.',
            'kind' => MilestoneKind::Test,
            'due_on' => '2026-10-03',
        ]);

        $plan = (new StudentPlan)->for($user, CarbonImmutable::parse('2026-03-10'));
        $senior = collect($plan['sections'])->firstWhere('key', 'grade-12');

        $this->assertContains('Sit the SAT.', array_column($senior['milestones'], 'title'));
    }

    /**
     * A deadline dated before the plan even opens is the one it is most
     * urgent to see, so it lands in the section the student is standing in
     * rather than falling off the bottom of the view.
     */
    #[Test]
    public function a_date_from_before_the_plan_began_is_not_dropped(): void
    {
        $user = User::factory()->create(['grade_level' => 11]);

        Milestone::factory()->for($user)->create([
            'title' => 'Ask Mr Alvarez for the reference.',
            'due_on' => '2025-06-01',
        ]);

        $plan = (new StudentPlan)->for($user, CarbonImmutable::parse('2026-03-10'));
        $first = $plan['sections'][0];

        $this->assertSame('2025-08-01', $first['starts_on'], 'The plan does not start where this test assumes.');

        $entry = collect($first['milestones'])->firstWhere('title', 'Ask Mr Alvarez for the reference.');

        $this->assertNotNull($entry, 'A deadline older than the plan vanished instead of surfacing.');
        $this->assertNotNull($entry['window']);
    }

    /**
     * Finishing a year happens whether or not anybody acts on it, so it is
     * computed and carries no affordance. A student cannot be behind on the
     * passage of time.
     */
    #[Test]
    public function the_things_that_happen_anyway_are_not_tasks(): void
    {
        $plan = (new StudentPlan)->for(
            User::factory()->create(['grade_level' => 11]),
            CarbonImmutable::parse('2026-03-10'),
        );

        $passages = collect($plan['sections'])
            ->flatMap(fn (array $section) => $section['milestones'])
            ->where('kind', MilestoneKind::Passage->label());

        $this->assertNotEmpty($passages, 'No passages at all would pass the assertions below vacuously.');
        $this->assertContains('Graduate high school', $passages->pluck('title')->all());

        foreach ($passages as $passage) {
            $this->assertFalse($passage['is_yours_to_do']);
            $this->assertNull($passage['id'], 'A passage is addressable, so something can be asked to tick it.');
            $this->assertNull($passage['window']);
        }
    }

    /**
     * The one rule that stops a wide view becoming the friction it exists to
     * remove.
     */
    #[Test]
    public function the_plan_carries_one_action_and_milestones_are_not_actions(): void
    {
        $user = $this->junior();
        (new ActionQueue)->assign($user, 'Email the shop and ask if you can come in on Saturday.');

        Milestone::factory()->for($user)->count(3)->create(['due_on' => now()->addMonths(3)->toDateString()]);

        $plan = (new StudentPlan)->for($user);

        $this->assertIsArray($plan['action']);
        $this->assertArrayNotHasKey('actions', $plan);

        $milestones = collect($plan['sections'])->flatMap(fn (array $s) => $s['milestones']);

        $this->assertNotEmpty($milestones);

        foreach ($milestones as $milestone) {
            $this->assertArrayNotHasKey('rationale', $milestone, 'A milestone is dressed as an action.');
        }
    }

    /**
     * No counts, no totals, no percentage, no bar. A plan that scores itself
     * is a report card, and a report card is read by a parent rather than
     * used by a student.
     */
    #[Test]
    public function nothing_in_the_plan_is_counted_or_scored(): void
    {
        $user = $this->junior();
        Milestone::factory()->for($user)->count(2)->create(['due_on' => now()->addMonth()->toDateString()]);
        Milestone::factory()->for($user)->done()->create(['due_on' => now()->addMonth()->toDateString()]);

        $flat = json_decode(json_encode((new StudentPlan)->for($user)), true);
        $keys = [];
        array_walk_recursive($flat, function ($value, $key) use (&$keys) {
            $keys[] = (string) $key;
        });

        foreach (['total', 'completed', 'count', 'percent', 'progress', 'score', 'remaining'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "The plan reports '{$forbidden}'.");
        }
    }

    /**
     * A date that has gone by is a fact about the calendar, computed every
     * time, never stored as a verdict — and it arrives with a move attached.
     * There is no "missed" status for the same reason there is no streak on a
     * habit.
     */
    #[Test]
    public function a_closed_window_is_a_fact_with_a_move_not_a_failure(): void
    {
        $this->assertNotContains('missed', MilestoneStatus::values());
        $this->assertNotContains('failed', MilestoneStatus::values());

        $user = $this->junior();
        Milestone::factory()->for($user)->create([
            'title' => 'Send the application.',
            'due_on' => now()->subWeek()->toDateString(),
        ]);

        $entry = collect((new StudentPlan)->for($user)['sections'])
            ->flatMap(fn (array $s) => $s['milestones'])
            ->firstWhere('title', 'Send the application.');

        $this->assertNotNull($entry['window']);
        $this->assertStringContainsString('set it down', $entry['window']);
        $this->assertSame('Not started', $entry['status'], 'A closed window was written into the record.');
    }

    /**
     * Setting something down on purpose is a decision, not a lapse, and it is
     * settled — so nothing chases it afterwards.
     */
    #[Test]
    public function a_milestone_set_down_on_purpose_is_left_alone(): void
    {
        $user = $this->junior();
        $milestone = Milestone::factory()->for($user)->putDown()->create([
            'title' => 'Sit the SAT.',
            'due_on' => now()->subWeek()->toDateString(),
        ]);

        $this->assertNotNull($milestone->fresh()->settled_at);

        $entry = collect((new StudentPlan)->for($user)['sections'])
            ->flatMap(fn (array $s) => $s['milestones'])
            ->firstWhere('title', 'Sit the SAT.');

        $this->assertTrue($entry['settled']);
        $this->assertNull($entry['window'], 'A milestone set down on purpose is still being chased.');
    }

    #[Test]
    public function only_the_student_moves_their_own_milestone(): void
    {
        $this->enableCoach();
        $user = $this->junior();
        $stranger = $this->junior();
        $milestone = Milestone::factory()->for($user)->create(['due_on' => now()->addMonth()->toDateString()]);

        $this->actingAs($stranger)
            ->patch("/milestones/{$milestone->id}", ['status' => 'done'])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch("/milestones/{$milestone->id}", ['status' => 'done'])
            ->assertRedirect();

        $this->assertSame(MilestoneStatus::Done, $milestone->fresh()->status);
    }

    /**
     * The tool refuses a guess rather than inventing a date, because an
     * invented deadline in a sixteen-year-old's plan is worse than no
     * deadline: they will believe it.
     */
    #[Test]
    public function the_coach_cannot_put_a_made_up_date_in_the_plan(): void
    {
        $user = $this->junior();
        $tool = new SetMilestoneTool($user);

        foreach (['sometime in the fall', '2026-13-45', ''] as $guess) {
            $result = json_decode($tool->handle(new ToolRequest([
                'title' => 'Sit the SAT.',
                'kind' => 'test',
                'due_on' => $guess,
                'why' => 'Because the schools you named all ask for it.',
            ])), true);

            $this->assertFalse($result['recorded'], "Accepted '{$guess}' as a date.");
            $this->assertStringContainsString('date', $result['guidance']);
        }

        $this->assertSame(0, Milestone::count());
    }

    /**
     * `passage` is in the enum and not in the tool. A model that could write
     * one could hand a student a task for turning seventeen.
     */
    #[Test]
    public function the_coach_cannot_write_a_passage(): void
    {
        $this->assertNotContains(MilestoneKind::Passage->value, SetMilestoneTool::kinds());

        $result = json_decode((new SetMilestoneTool($this->junior()))->handle(new ToolRequest([
            'title' => 'Finish junior year.',
            'kind' => 'passage',
            'due_on' => now()->addMonths(2)->toDateString(),
            'why' => 'It is on the calendar.',
        ])), true);

        $this->assertFalse($result['recorded']);
        $this->assertSame(0, Milestone::count());
    }

    #[Test]
    public function the_plan_is_a_place_now_that_it_has_a_route(): void
    {
        $this->enableCoach();
        $user = $this->junior();

        $response = $this->actingAs($user)->get('/plan');
        $response->assertOk();

        $this->assertContains(
            'plan',
            collect($response->viewData('page')['props']['places'])->pluck('key')->all(),
            'Registering the route did not make the place appear.',
        );
    }
}
