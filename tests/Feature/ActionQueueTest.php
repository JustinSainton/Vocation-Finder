<?php

namespace Tests\Feature;

use App\Ai\Tools\AssignActionTool;
use App\Ai\Tools\GetCurrentActionTool;
use App\Enums\ActionStatus;
use App\Enums\GapStatus;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Gap;
use App\Models\User;
use App\Support\ActionQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * One action, never a list.
 *
 * The vision's success test is whether a student finishes able to describe, in
 * their own words, one thing they are going to do. A list of twenty is the
 * decision friction this product exists to remove, so the constraint is
 * enforced at three levels: the database, the queue service, and the text of
 * the action itself.
 */
class ActionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected ActionQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new ActionQueue;
    }

    protected function student(): User
    {
        return User::factory()->create();
    }

    protected function gapFor(User $user, GapType $type = GapType::Relationships): Gap
    {
        return Gap::create([
            'user_id' => $user->id,
            'type' => $type,
            'summary' => 'Nobody in their life does this work.',
        ]);
    }

    public function test_it_assigns_one_action_a_student_can_describe(): void
    {
        $student = $this->student();

        $action = $this->queue->assign(
            $student,
            'Ask your aunt what the hardest week of her job looks like.',
            'It tells you what the work costs, not just what it pays.',
        );

        $this->assertSame(ActionStatus::Active, $action->status);
        $this->assertTrue($action->isActive());
        $this->assertTrue($this->queue->current($student)->is($action));
    }

    public function test_it_refuses_a_second_action_while_one_is_in_progress(): void
    {
        $student = $this->student();
        $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has an action in progress');

        $this->queue->assign($student, 'Email the volunteer coordinator at the clinic.');
    }

    /**
     * The service refusal is the friendly guard. This is the one that holds
     * when something bypasses it — the constraint is the product thesis, so it
     * belongs in the database too.
     */
    public function test_the_database_itself_rejects_a_second_active_action(): void
    {
        $student = $this->student();
        $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.');

        $this->expectException(QueryException::class);

        Action::create([
            'user_id' => $student->id,
            'title' => 'Sneak a second one in.',
            'status' => ActionStatus::Active,
        ]);
    }

    public function test_settling_an_action_frees_the_queue_for_the_next_one(): void
    {
        $student = $this->student();
        $first = $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.');

        $this->queue->complete($first, 'She said the paperwork is the worst part.');
        $second = $this->queue->assign($student, 'Email the volunteer coordinator at the clinic.');

        $this->assertSame(ActionStatus::Completed, $first->fresh()->status);
        $this->assertTrue($second->isActive());
        $this->assertSame(2, $student->actions()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function disguisedLists(): array
    {
        return [
            'newline separated' => ["Call the clinic.\nEmail the college."],
            'semicolon separated' => ['Call the clinic; email the college.'],
            'bullet points' => ['Do these: - call the clinic - email the college'],
            'numbered' => ['1. Call the clinic 2. Email the college'],
            'first then' => ['First call the clinic, then email the college.'],
        ];
    }

    /**
     * A list wearing a queue's clothing reintroduces exactly the friction the
     * queue removes, one item at a time.
     */
    #[DataProvider('disguisedLists')]
    public function test_it_refuses_an_action_that_is_really_a_list(string $title): void
    {
        $this->assertFalse(ActionQueue::isSingleAction($title));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('That is a list');

        $this->queue->assign($this->student(), $title);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function singleActions(): array
    {
        return [
            'several clauses, one act' => ['Ask your aunt\'s friend who is a nurse what her hardest week actually looks like.'],
            'contains a number' => ['Sit in on 1 shift at the animal shelter.'],
            'contains the word and' => ['Go to the open evening and stay for the questions afterwards.'],
            'mentions first' => ['Ask her what she wishes she had known first.'],
            'a place and a person' => ['Visit the workshop on Saturday and ask the foreman what he looks for.'],
        ];
    }

    /**
     * The check must not cry wolf. One action can be a whole sentence with
     * clauses in it, and rejecting those would make the guard unusable.
     */
    #[DataProvider('singleActions')]
    public function test_it_accepts_a_single_action_with_more_than_one_clause(string $title): void
    {
        $this->assertTrue(ActionQueue::isSingleAction($title), "Wrongly rejected: {$title}");
        $this->assertTrue($this->queue->assign($this->student(), $title)->isActive());
    }

    public function test_it_refuses_a_plan_dressed_as_a_step(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('That is a plan, not a step');

        $this->queue->assign($this->student(), str_repeat('keep going ', 40));
    }

    public function test_it_refuses_an_empty_action(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('needs to say what to do');

        $this->queue->assign($this->student(), '   ');
    }

    /**
     * Gaps are what actions are for. A completed action that left its gap open
     * would mean the student did the thing and the system did not notice.
     */
    public function test_completing_an_action_closes_the_gap_it_aimed_at(): void
    {
        $student = $this->student();
        $gap = $this->gapFor($student);

        $action = $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.', null, $gap);
        $this->queue->complete($action, 'She told me about the paperwork.');

        $this->assertSame(GapStatus::Closed, $gap->fresh()->status);
        $this->assertNotNull($gap->fresh()->closed_at);
    }

    /**
     * Not doing something is a fact about the step, never a verdict on the
     * student — so the gap stays open and waits for a better step.
     */
    public function test_skipping_an_action_leaves_its_gap_open(): void
    {
        $student = $this->student();
        $gap = $this->gapFor($student);

        $action = $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.', null, $gap);
        $this->queue->skip($action, 'She moved away.');

        $this->assertSame(GapStatus::Open, $gap->fresh()->status);
        $this->assertSame(ActionStatus::Skipped, $action->fresh()->status);
        $this->assertNull($this->queue->current($student));
    }

    public function test_an_action_cannot_be_deleted_only_settled(): void
    {
        $action = $this->queue->assign($this->student(), 'Ask your aunt what her hardest week looks like.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('never deleted');

        $action->delete();
    }

    public function test_it_refuses_to_aim_an_action_at_another_students_gap(): void
    {
        $mine = $this->student();
        $theirs = $this->student();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belongs to a different student');

        $this->queue->assign($mine, 'Ask your aunt what her hardest week looks like.', null, $this->gapFor($theirs));
    }

    public function test_two_students_can_each_have_their_own_active_action(): void
    {
        $first = $this->student();
        $second = $this->student();

        $this->queue->assign($first, 'Ask your aunt what her hardest week looks like.');
        $this->queue->assign($second, 'Email the volunteer coordinator at the clinic.');

        $this->assertNotNull($this->queue->current($first));
        $this->assertNotNull($this->queue->current($second));
    }

    public function test_the_assign_tool_returns_guidance_rather_than_failing_on_a_list(): void
    {
        $student = $this->student();

        $payload = json_decode((new AssignActionTool($student))->handle(new Request([
            'title' => 'Call the clinic; email the college.',
            'rationale' => 'Both would help.',
            'gap_id' => '',
        ])), true);

        $this->assertFalse($payload['assigned']);
        $this->assertStringContainsString('That is a list', $payload['guidance']);
        $this->assertSame(0, $student->actions()->count());
    }

    public function test_the_assign_tool_links_the_action_to_the_gap_it_aims_at(): void
    {
        $student = $this->student();
        $gap = $this->gapFor($student);

        $payload = json_decode((new AssignActionTool($student))->handle(new Request([
            'title' => 'Ask your aunt what her hardest week looks like.',
            'rationale' => 'You have nobody to ask yet.',
            'gap_id' => $gap->id,
        ])), true);

        $this->assertTrue($payload['assigned']);
        $this->assertTrue($student->actions()->first()->gap->is($gap));
    }

    public function test_the_assign_tool_ignores_a_gap_belonging_to_someone_else(): void
    {
        $student = $this->student();

        $payload = json_decode((new AssignActionTool($student))->handle(new Request([
            'title' => 'Ask your aunt what her hardest week looks like.',
            'rationale' => 'You have nobody to ask yet.',
            'gap_id' => $this->gapFor($this->student())->id,
        ])), true);

        $this->assertTrue($payload['assigned']);
        $this->assertNull($student->actions()->first()->gap_id);
    }

    public function test_the_read_tool_tells_the_coach_not_to_stack_a_second_action(): void
    {
        $student = $this->student();
        $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.');

        $payload = json_decode((new GetCurrentActionTool($student))->handle(new Request([])), true);

        $this->assertTrue($payload['has_action']);
        $this->assertStringContainsString('Do not assign another one on top', $payload['guidance']);
    }

    public function test_the_read_tool_reports_what_they_finished_before(): void
    {
        $student = $this->student();
        $first = $this->queue->assign($student, 'Ask your aunt what her hardest week looks like.');
        $this->queue->complete($first, 'She said the paperwork is the worst part.');

        $payload = json_decode((new GetCurrentActionTool($student))->handle(new Request([])), true);

        $this->assertFalse($payload['has_action']);
        $this->assertSame('She said the paperwork is the worst part.', $payload['previous'][0]['they_said']);
    }
}
