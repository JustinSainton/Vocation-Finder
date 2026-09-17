<?php

namespace Tests\Feature;

use App\Ai\Tools\GetHabitsTool;
use App\Ai\Tools\PrescribeHabitTool;
use App\Enums\GapType;
use App\Enums\HabitCadence;
use App\Models\FeatureFlag;
use App\Models\Gap;
use App\Models\Habit;
use App\Models\User;
use App\Support\HabitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request as ToolRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two surfaces a habit reaches: the coach prescribing it, and the student
 * answering for it. They are deliberately different shapes — the coach gets
 * counts so it can tell a habit is the wrong size; the student gets words.
 */
class HabitSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Check-in lives inside the `pathway_coach` flag with the coach itself.
     * Unlike the brain export — which is deliberately outside every gate,
     * because a lapse freezes the brain rather than seizing it — a habit only
     * exists because the coach prescribed it, so there is nothing to answer
     * for when the coach is off.
     */
    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    protected function tool(array $input): ToolRequest
    {
        return new ToolRequest($input);
    }

    protected function gapFor(User $user): Gap
    {
        return Gap::create([
            'user_id' => $user->id,
            'type' => GapType::Habits,
            'summary' => 'They have never tried it on an ordinary day.',
        ]);
    }

    /**
     * The refusal has to come back as guidance. A stack trace produces an
     * apology and another attempt at the same mistake; "that is not one of
     * this student's gaps" produces a GetGapsTool call.
     */
    #[Test]
    public function prescribing_without_a_real_gap_is_refused_as_guidance(): void
    {
        $student = User::factory()->create();

        $result = json_decode((new PrescribeHabitTool($student))->handle($this->tool([
            'title' => 'Write down one thing you noticed.',
            'gap_id' => 'not-a-gap',
            'cadence' => 'daily',
            'why' => 'So the next conversation has something real in it.',
        ])), true);

        $this->assertFalse($result['prescribed']);
        $this->assertStringContainsString('gap', $result['guidance']);
        $this->assertSame(0, Habit::count());
    }

    #[Test]
    public function a_habit_aimed_at_another_students_gap_is_refused(): void
    {
        $student = User::factory()->create();
        $someoneElse = User::factory()->create();

        $result = json_decode((new PrescribeHabitTool($student))->handle($this->tool([
            'title' => 'Write down one thing you noticed.',
            'gap_id' => $this->gapFor($someoneElse)->id,
            'cadence' => 'daily',
            'why' => 'Because.',
        ])), true);

        $this->assertFalse($result['prescribed']);
        $this->assertSame(0, Habit::count());
    }

    #[Test]
    public function a_prescribed_habit_lands_against_its_gap(): void
    {
        $student = User::factory()->create();
        $gap = $this->gapFor($student);

        $result = json_decode((new PrescribeHabitTool($student))->handle($this->tool([
            'title' => 'Write down one thing you noticed about the work you did that day.',
            'gap_id' => $gap->id,
            'cadence' => 'weekdays',
            'why' => 'So the next conversation has something real in it.',
        ])), true);

        $this->assertTrue($result['prescribed']);

        $habit = Habit::firstOrFail();
        $this->assertSame($gap->id, $habit->gap_id);
        $this->assertSame(HabitCadence::Weekdays, $habit->cadence);
    }

    /**
     * The coach is told what to do with a stalled habit at the point it reads
     * one, not only in the system prompt six thousand tokens earlier.
     */
    #[Test]
    public function the_coach_is_told_not_to_count_misses_back_at_the_student(): void
    {
        $student = User::factory()->create();
        (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            'Write down one thing you noticed.',
            HabitCadence::Daily,
        );

        $result = json_decode((new GetHabitsTool($student))->handle($this->tool([])), true);

        $this->assertCount(1, $result['habits']);
        $this->assertArrayHasKey('occasions_kept', $result['habits'][0]);
        $this->assertStringContainsStringIgnoringCase('wrong size', $result['guidance']);
        $this->assertStringContainsStringIgnoringCase('never count their misses', $result['guidance']);
    }

    #[Test]
    public function a_student_answers_for_their_own_habit(): void
    {
        $this->enableCoach();

        $student = User::factory()->create();
        $habit = (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            'Write down one thing you noticed.',
            HabitCadence::Daily,
        );

        $this->actingAs($student)
            ->post("/habits/{$habit->id}/check-in", ['happened' => true])
            ->assertRedirect();

        $this->assertTrue($habit->checkIns()->sole()->happened);
    }

    /**
     * ⚠️ A tracker someone else can fill in is a compliance report. There is
     * no route by which a coach, a counsellor or a parent records that a
     * student's habit happened.
     */
    #[Test]
    public function nobody_else_can_answer_for_them(): void
    {
        $this->enableCoach();

        $student = User::factory()->create();
        $someoneElse = User::factory()->create();

        $habit = (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            'Write down one thing you noticed.',
            HabitCadence::Daily,
        );

        $this->actingAs($someoneElse)
            ->post("/habits/{$habit->id}/check-in", ['happened' => true])
            ->assertForbidden();

        $this->assertSame(0, $habit->checkIns()->count());

        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'habit'))
            ->map(fn ($route) => $route->uri());

        $this->assertTrue($routes->isNotEmpty(), 'No habit routes; this would pass vacuously.');

        foreach ($routes as $uri) {
            foreach (['{user}', '{student}', '{member}'] as $someoneElsesId) {
                $this->assertStringNotContainsString($someoneElsesId, $uri);
            }
        }
    }

    /**
     * Saying "not today" must be as cheap as saying "I did it". Requiring a
     * reason to answer honestly is how a form teaches someone to stop
     * answering.
     */
    #[Test]
    public function a_miss_can_be_recorded_without_explaining_it(): void
    {
        $this->enableCoach();

        $student = User::factory()->create();
        $habit = (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            'Write down one thing you noticed.',
            HabitCadence::Daily,
        );

        $this->actingAs($student)
            ->post("/habits/{$habit->id}/check-in", ['happened' => false])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($habit->checkIns()->sole()->happened);
    }
}
