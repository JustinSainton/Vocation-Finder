<?php

namespace Tests\Feature;

use App\Ai\Tools\GetGapsTool;
use App\Ai\Tools\RecordGapTool;
use App\Enums\GapStatus;
use App\Enums\GapType;
use App\Models\Assessment;
use App\Models\Gap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The six gap types as first-class records.
 *
 * The vision's claim is that most students cannot name which gap is theirs,
 * and that naming it is most of the help — a student who believes they lack
 * information, when what they actually lack is anyone to ask, will read more
 * and move no closer. That only works if the naming survives the conversation.
 */
class GapTest extends TestCase
{
    use RefreshDatabase;

    protected function student(): User
    {
        return User::factory()->create();
    }

    protected function recordGap(User $user, string $type, string $summary = 'You have not spoken to anyone who does this work.'): array
    {
        return json_decode((new RecordGapTool($user))->handle(new Request([
            'type' => $type,
            'summary' => $summary,
            'evidence' => 'I do not really know anyone who does that.',
        ])), true);
    }

    public function test_there_are_exactly_six_gap_types(): void
    {
        $this->assertSame([
            'information', 'access', 'finances', 'habits', 'relationships', 'future_outlook',
        ], GapType::values());
    }

    /**
     * Blueprint 10.5's next-step forms are not interchangeable: reading an
     * article does not close an access gap. A gap's type has to constrain what
     * counts as progress on it, or the coach will suggest the same move for
     * all six.
     */
    #[DataProvider('gapTypes')]
    public function test_every_gap_type_describes_itself_and_names_a_distinct_closing_move(string $value): void
    {
        $type = GapType::from($value);

        $this->assertNotSame('', $type->label());
        $this->assertNotSame('', $type->description());
        $this->assertNotSame('', $type->closingMove());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function gapTypes(): array
    {
        return array_combine(
            GapType::values(),
            array_map(fn (string $value) => [$value], GapType::values()),
        );
    }

    public function test_closing_moves_are_distinct_across_the_six(): void
    {
        $moves = array_map(fn (GapType $type) => $type->closingMove(), GapType::cases());

        $this->assertCount(6, array_unique($moves));
    }

    /**
     * A gap type must never read as a verdict on the person. These are
     * circumstances, and the wording has to keep them that way.
     */
    public function test_no_gap_description_blames_the_student(): void
    {
        foreach (GapType::cases() as $type) {
            $text = strtolower($type->description().' '.$type->closingMove());

            foreach (['lazy', 'unmotivated', 'failure', 'deficient', 'incapable', 'weakness'] as $blame) {
                $this->assertStringNotContainsString($blame, $text);
            }
        }
    }

    public function test_the_coach_can_record_a_gap_that_outlives_the_conversation(): void
    {
        $student = $this->student();

        $result = $this->recordGap($student, 'relationships');

        $this->assertTrue($result['recorded']);
        $this->assertSame('relationships', $result['type']);
        $this->assertNotEmpty($result['closing_move']);

        $gap = $student->gaps()->first();
        $this->assertSame(GapType::Relationships, $gap->type);
        $this->assertSame(GapStatus::Open, $gap->status);
        $this->assertSame('I do not really know anyone who does that.', $gap->evidence);
    }

    public function test_it_refuses_a_gap_type_outside_the_six(): void
    {
        $student = $this->student();

        $result = $this->recordGap($student, 'motivation');

        $this->assertFalse($result['recorded']);
        $this->assertSame(0, $student->gaps()->count());
    }

    /**
     * The same gap named twice in one conversation is one gap. Recording it
     * again would inflate a student's list of problems without adding one.
     */
    public function test_it_does_not_open_a_second_gap_of_a_type_already_open(): void
    {
        $student = $this->student();

        $this->recordGap($student, 'access');
        $second = $this->recordGap($student, 'access');

        $this->assertFalse($second['recorded']);
        $this->assertSame(1, $student->gaps()->count());
        $this->assertStringContainsString('already open', $second['guidance']);
    }

    public function test_a_closed_gap_does_not_block_the_same_gap_reopening_later(): void
    {
        $student = $this->student();

        $this->recordGap($student, 'finances');
        $student->gaps()->first()->close();

        $this->assertTrue($this->recordGap($student, 'finances')['recorded']);
        $this->assertSame(2, $student->gaps()->count());
    }

    /**
     * The brain persistence policy at model level: closing and deleting are
     * not the same act, and only one of them is allowed. A record of having
     * closed something is the most encouraging thing this product can show a
     * student two years later.
     */
    public function test_a_gap_cannot_be_deleted_only_closed(): void
    {
        $student = $this->student();
        $this->recordGap($student, 'habits');
        $gap = $student->gaps()->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gaps are closed, never deleted');

        $gap->delete();
    }

    public function test_closing_a_gap_records_when_it_closed(): void
    {
        $student = $this->student();
        $this->recordGap($student, 'information');
        $gap = $student->gaps()->first();

        $gap->close();

        $this->assertSame(GapStatus::Closed, $gap->fresh()->status);
        $this->assertNotNull($gap->fresh()->closed_at);
        $this->assertFalse($gap->fresh()->isActive());
    }

    public function test_a_gap_being_tested_still_counts_as_active(): void
    {
        $student = $this->student();
        $this->recordGap($student, 'future_outlook');
        $gap = $student->gaps()->first();

        $gap->markTesting();

        $this->assertTrue($gap->fresh()->isActive());
        $this->assertSame(1, $student->gaps()->active()->count());
    }

    /**
     * Losing the evidence for a gap must never delete the gap. Nothing
     * upstream may destroy a student's own record of what stood in their way.
     */
    public function test_deleting_the_source_assessment_leaves_the_gap_standing(): void
    {
        $student = $this->student();

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $gap = Gap::create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'type' => GapType::Access,
            'summary' => 'No way in yet.',
        ]);

        $assessment->forceDelete();

        $this->assertNotNull($gap->fresh());
        $this->assertNull($gap->fresh()->assessment_id);
    }

    public function test_the_read_tool_reports_open_closed_and_unexplored_gaps(): void
    {
        $student = $this->student();
        $this->recordGap($student, 'access');
        $this->recordGap($student, 'finances');
        $student->gaps()->ofType(GapType::Finances)->first()->close();

        $payload = json_decode((new GetGapsTool($student))->handle(new Request([])), true);

        $this->assertSame(['access'], array_column($payload['open'], 'type'));
        $this->assertSame(['finances'], array_column($payload['closed'], 'type'));
        $this->assertEqualsCanonicalizing(
            ['information', 'habits', 'relationships', 'future_outlook'],
            $payload['unexplored'],
        );
    }

    /**
     * A student who closed an access gap last spring should not be asked about
     * it again as though nothing happened. Continuity across months is what a
     * coach with no memory cannot fake.
     */
    public function test_the_read_tool_tells_the_coach_not_to_reopen_closed_gaps(): void
    {
        $student = $this->student();
        $this->recordGap($student, 'habits');

        $payload = json_decode((new GetGapsTool($student))->handle(new Request([])), true);

        $this->assertStringContainsString('Do not reopen what is closed', $payload['guidance']);
    }

    public function test_the_read_tool_tells_the_coach_not_to_name_a_gap_before_asking(): void
    {
        $payload = json_decode((new GetGapsTool($this->student()))->handle(new Request([])), true);

        $this->assertSame([], $payload['open']);
        $this->assertSame(GapType::values(), $payload['unexplored']);
        $this->assertStringContainsString('Ask about their situation', $payload['guidance']);
    }

    public function test_gaps_are_scoped_to_their_own_student(): void
    {
        $mine = $this->student();
        $theirs = $this->student();

        $this->recordGap($mine, 'access');

        $this->assertSame(1, $mine->gaps()->count());
        $this->assertSame(0, $theirs->gaps()->count());
        $this->assertSame([], json_decode((new GetGapsTool($theirs))->handle(new Request([])), true)['open']);
    }
}
