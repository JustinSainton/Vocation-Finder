<?php

namespace Tests\Feature;

use App\Ai\Tools\GetReadinessTool;
use App\Enums\ConfidenceLevel;
use App\Enums\FactorStanding;
use App\Enums\GapType;
use App\Enums\ReadinessFactor;
use App\Enums\ReadinessLevel;
use App\Models\Assessment;
use App\Models\Gap;
use App\Models\ParentConsent;
use App\Models\ReadinessSnapshot;
use App\Models\User;
use App\Models\VocationalProfile;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\ReadinessCalculator;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Readiness to Change.
 *
 * The vision's requirement is three things on the dashboard: the level, what
 * moves it, and how it has changed. The properties tested here are the ones
 * that make it a metric a student works at rather than a grade they receive —
 * it never shows a number, naming an obstacle moves it up, and setting a step
 * aside is never punished.
 */
class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected ReadinessCalculator $readiness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->readiness = new ReadinessCalculator;
    }

    protected function student(): User
    {
        $student = User::factory()->paying()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);

        ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ])->grant();

        return $student->fresh();
    }

    protected function profileFor(User $student, ConfidenceLevel $confidence): void
    {
        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'opening_synthesis' => 'You keep going back to the part nobody has figured out yet.',
            'confidence_level' => $confidence,
        ]);
    }

    protected function gapFor(User $student, GapType $type = GapType::Information): Gap
    {
        return Gap::create([
            'user_id' => $student->id,
            'type' => $type,
            'summary' => 'They do not know what the work actually costs day to day.',
        ]);
    }

    public function test_a_student_who_has_done_nothing_is_considering_not_failing(): void
    {
        $level = $this->readiness->evaluate($this->student())['level'];

        $this->assertSame(ReadinessLevel::Considering, $level);
        $this->assertStringContainsString('everyone starts there', $level->description());
    }

    /**
     * The central product-values decision in this feature. A student who says
     * they cannot afford the program has learned something true; scoring them
     * down for saying it would teach them not to say it.
     */
    public function test_naming_an_obstacle_moves_readiness_up_not_down(): void
    {
        $student = $this->student();
        $before = $this->readiness->evaluate($student)['level'];

        $this->gapFor($student, GapType::Finances);

        $after = $this->readiness->evaluate($student->fresh())['level'];

        $this->assertGreaterThanOrEqual($before->rank(), $after->rank());
        $this->assertSame(
            FactorStanding::Beginning->value,
            $this->readiness->evaluate($student->fresh())['factors'][ReadinessFactor::ObstaclesNamed->value],
        );
    }

    public function test_finishing_actions_moves_it_further_than_naming_things(): void
    {
        $student = $this->student();
        $queue = new ActionQueue;

        foreach (range(1, 3) as $index) {
            $action = $queue->assign($student->fresh(), "Ask someone what week {$index} of that job is like.");
            $queue->complete($action, 'she said the hard part is telling people bad news and i think i could do that');
        }

        $factors = $this->readiness->evaluate($student->fresh())['factors'];

        $this->assertSame(FactorStanding::Building->value, $factors[ReadinessFactor::Testing->value]);
    }

    public function test_a_student_doing_the_work_across_every_factor_is_moving(): void
    {
        $student = $this->student();
        $this->profileFor($student, ConfidenceLevel::Strong);
        $queue = new ActionQueue;

        foreach (range(1, 16) as $index) {
            (new BrainCapture)->captureDirect($student->fresh(), "the thing i keep coming back to is number {$index} of these");
        }

        foreach (range(1, 5) as $index) {
            $gap = $this->gapFor($student->fresh(), GapType::cases()[$index % 6]);
            $gap->close();
        }

        foreach (range(1, 6) as $index) {
            $action = $queue->assign($student->fresh(), "Ask someone what week {$index} of that job is like.");
            $queue->complete($action, 'it went better than i expected and i want to do it again honestly');
        }

        $this->assertSame(ReadinessLevel::Moving, $this->readiness->evaluate($student->fresh())['level']);
    }

    /**
     * DESIGN.md bans percentages, dials and match scores. Readiness is the
     * most tempting place to break that, because a number is so much easier to
     * render than a sentence.
     */
    public function test_nothing_the_student_sees_contains_a_number(): void
    {
        $student = $this->student();
        $this->profileFor($student, ConfidenceLevel::Moderate);
        $this->gapFor($student);

        $explained = $this->readiness->explain($student->fresh());
        unset($explained['history']);

        $this->assertDoesNotMatchRegularExpression('/\d/', json_encode($explained));
    }

    public function test_the_student_facing_copy_passes_the_red_team_lint(): void
    {
        $student = $this->student();
        $this->profileFor($student, ConfidenceLevel::Moderate);

        $copy = json_encode($this->readiness->explain($student->fresh()));

        $this->assertSame([], RedTeamLint::blocking(RedTeamLint::inspect($copy)));
    }

    /**
     * "What moves it" must be one thing. A student handed five things to work
     * on is back in the decision friction this product exists to remove.
     */
    public function test_what_moves_it_names_exactly_one_thing(): void
    {
        $student = $this->student();

        $move = $this->readiness->explain($student)['what_moves_it'];

        $this->assertNotSame('', $move);
        $this->assertSame(1, preg_match_all('/[.!?]/u', $move));
    }

    public function test_what_moves_it_points_at_the_thinnest_factor(): void
    {
        $student = $this->student();
        $this->profileFor($student, ConfidenceLevel::Strong);

        foreach (range(1, 16) as $index) {
            (new BrainCapture)->captureDirect($student->fresh(), "the thing i keep coming back to is number {$index} of these");
        }

        $move = $this->readiness->explain($student->fresh())['what_moves_it'];

        $this->assertNotSame(ReadinessFactor::SelfKnowledge->move(), $move);
    }

    public function test_it_decomposes_into_factors_each_with_its_own_move(): void
    {
        $factors = $this->readiness->explain($this->student())['factors'];

        $this->assertCount(count(ReadinessFactor::cases()), $factors);

        foreach ($factors as $factor) {
            $this->assertNotSame('', $factor['label']);
            $this->assertNotSame('', $factor['move']);
            $this->assertNotSame('', $factor['standing']);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function factors(): array
    {
        return [
            'self knowledge' => ['self_knowledge'],
            'direction' => ['direction'],
            'obstacles named' => ['obstacles_named'],
            'obstacles cleared' => ['obstacles_cleared'],
            'testing' => ['testing'],
        ];
    }

    /**
     * A shared move across two factors would make the decomposition decorative.
     */
    #[DataProvider('factors')]
    public function test_every_factor_names_a_distinct_move(string $factor): void
    {
        $move = ReadinessFactor::from($factor)->move();
        $others = collect(ReadinessFactor::cases())
            ->reject(fn (ReadinessFactor $case) => $case->value === $factor)
            ->map(fn (ReadinessFactor $case) => $case->move());

        $this->assertFalse($others->contains($move));
    }

    public function test_finishing_an_action_records_a_point_in_the_history(): void
    {
        $student = $this->student();
        $queue = new ActionQueue;

        $action = $queue->assign($student, 'Ask your aunt what her hardest week looks like.');
        $queue->complete($action, 'she said the hard part is telling people bad news and i could do that');

        $history = $this->readiness->history($student->fresh());

        $this->assertNotEmpty($history);
        $this->assertSame('You finished something.', $history[0]['because']);
    }

    /**
     * A skipped step is information about the step, never a verdict on the
     * student. If setting something aside cost them, they would stop telling us
     * when a step was wrong.
     */
    public function test_skipping_a_step_never_lowers_readiness(): void
    {
        $student = $this->student();
        $queue = new ActionQueue;
        $this->gapFor($student);

        $before = $this->readiness->evaluate($student->fresh())['level'];
        $action = $queue->assign($student->fresh(), 'Ask your aunt what her hardest week looks like.');
        $queue->skip($action, 'she works nights and i could not reach her');

        $after = $this->readiness->evaluate($student->fresh())['level'];

        $this->assertGreaterThanOrEqual($before->rank(), $after->rank());
    }

    /**
     * A timeline where every page load adds a row is a log, not a history.
     */
    public function test_recording_an_unchanged_reading_does_not_add_to_the_history(): void
    {
        $student = $this->student();

        $this->readiness->record($student);
        $this->readiness->record($student);
        $this->readiness->record($student);

        $this->assertSame(1, ReadinessSnapshot::where('user_id', $student->id)->count());
    }

    public function test_history_is_append_only(): void
    {
        $student = $this->student();
        $snapshot = $this->readiness->record($student);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        $snapshot->update(['level' => ReadinessLevel::Moving]);
    }

    public function test_history_reads_oldest_first_so_change_is_legible(): void
    {
        $student = $this->student();
        $queue = new ActionQueue;

        $first = $this->readiness->record($student, 'Starting out.');
        $first->forceFill(['captured_at' => now()->subMonths(3)])->saveQuietly();

        $action = $queue->assign($student->fresh(), 'Ask your aunt what her hardest week looks like.');
        $queue->complete($action, 'she said the hard part is telling people bad news and i could do that');

        $history = $this->readiness->history($student->fresh());

        $this->assertSame('Starting out.', $history[0]['because']);
        $this->assertSame('You finished something.', $history[1]['because']);
    }

    public function test_the_coach_tool_refuses_to_frame_readiness_as_a_score(): void
    {
        $payload = json_decode((new GetReadinessTool($this->student()))->handle(new Request([])), true);

        $this->assertArrayHasKey('what_moves_it', $payload);
        $this->assertStringContainsString('not something they are', $payload['guidance']);
        $this->assertStringContainsString('never tell them it went down', $payload['guidance']);
    }

    public function test_direction_comes_from_the_engine_not_from_the_student(): void
    {
        $student = $this->student();
        $this->profileFor($student, ConfidenceLevel::Weak);

        $factors = $this->readiness->evaluate($student->fresh())['factors'];

        $this->assertSame(FactorStanding::NotYet->value, $factors[ReadinessFactor::Direction->value]);
    }
}
