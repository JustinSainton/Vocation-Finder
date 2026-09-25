<?php

namespace Tests\Unit;

use App\Jobs\AnalyzeAssessmentJob;
use App\Jobs\GenerateCurriculumJob;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use App\Support\RedTeamLint;
use App\Support\RedTeamViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class AnalyzeAssessmentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function dispatchCurriculumFor(Assessment $assessment): void
    {
        $job = new AnalyzeAssessmentJob($assessment);

        $method = new ReflectionMethod($job, 'dispatchCurriculum');
        $method->setAccessible(true);
        $method->invoke($job);
    }

    /**
     * Courses are a legacy surface that V1 does not publish. Queuing a job
     * that can only fail marks the student's pathway "failed" for a feature
     * they were never offered.
     */
    #[Test]
    public function it_does_not_queue_curriculum_generation_when_no_course_is_published(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->dispatchCurriculumFor($assessment);

        Bus::assertNotDispatched(GenerateCurriculumJob::class);
    }

    #[Test]
    public function it_queues_curriculum_generation_once_a_course_is_published(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        Course::factory()->create(['is_published' => true]);

        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->dispatchCurriculumFor($assessment);

        Bus::assertDispatched(GenerateCurriculumJob::class);
    }

    /**
     * By this point the profile is persisted and the result belongs to the
     * student. A downstream hand-off must never be able to unwind it.
     */
    #[Test]
    public function a_failing_hand_off_does_not_undo_a_completed_analysis(): void
    {
        Course::factory()->create(['is_published' => true]);

        $assessment = Assessment::create([
            'user_id' => User::factory()->create()->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue is down'));

        $this->dispatchCurriculumFor($assessment);

        $this->assertSame('completed', $assessment->fresh()->status);
    }

    #[Test]
    public function it_extracts_multiple_markdown_list_items_cleanly(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'extractListItems');
        $method->setAccessible(true);

        $items = $method->invoke($job, <<<'TEXT'
- **Architecture** — Designing schools and community spaces that dignify the people who use them.
- **Urban planning** — Shaping neighborhoods and civic systems with long-term stewardship in view.
- **Design-build leadership** — Combining design, execution, and team leadership in one practice.
TEXT);

        $this->assertSame([
            'Architecture — Designing schools and community spaces that dignify the people who use them.',
            'Urban planning — Shaping neighborhoods and civic systems with long-term stewardship in view.',
            'Design-build leadership — Combining design, execution, and team leadership in one practice.',
        ], $items);
    }

    #[Test]
    public function it_rejects_narratives_with_too_few_pathways_or_next_steps(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateParsedSections');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);

        $method->invoke($job, [
            'primary_pathways' => ['Only one pathway'],
            'next_steps' => ['Only one next step'],
        ]);
    }

    /**
     * A narrative with every required section present, used to isolate the
     * red-team pass from the structural checks that run alongside it.
     */
    protected function wellFormedNarrative(string $openingLine): string
    {
        return <<<TEXT
        ## Opening Synthesis

        {$openingLine}

        ## Vocational Orientation

        A recurring pattern in your answers is a pull toward people who are overlooked.

        ## Primary Pathways

        - **Nursing** — Direct care in a clinical setting.
        - **Teaching** — Explaining things to people who are stuck.
        - **Social work** — Sitting with people in hard seasons.

        ## Specific Considerations

        You have not yet tested any of these in a setting with real stakes.

        ## Next Steps

        1. Ask someone who does this work what their hardest week looks like.
        2. Volunteer somewhere that will let you sit with people.
        3. Write down what drained you and what did not.

        ## Ministry Integration

        Work of this kind is service to a neighbor, and it is ministry in the ordinary
        sense the tradition means: the care of people in front of you, done well and
        done faithfully, without needing a pulpit to make it count. What you carry is
        useful to someone, and the question worth testing is who.
        TEXT;
    }

    #[Test]
    public function it_refuses_a_narrative_that_claims_to_speak_for_god(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateNarrative');
        $method->setAccessible(true);

        $this->expectException(RedTeamViolation::class);

        $method->invoke($job, $this->wellFormedNarrative('God has called you to nursing.'));
    }

    #[Test]
    public function it_refuses_a_narrative_that_reduces_the_student_to_a_percentage(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateNarrative');
        $method->setAccessible(true);

        $this->expectException(RedTeamViolation::class);

        $method->invoke($job, $this->wellFormedNarrative('You are a 92% match for healthcare.'));
    }

    /**
     * Off-voice language is logged, not refused. Withholding a student's
     * result over a consultant-ism would be its own failure.
     */
    #[Test]
    public function it_ships_a_narrative_whose_only_flaw_is_off_voice_language(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateNarrative');
        $method->setAccessible(true);

        $method->invoke($job, $this->wellFormedNarrative('You could leverage this strength further.'));

        $this->assertTrue(true);
    }

    #[Test]
    public function it_accepts_a_narrative_written_in_the_approved_voice(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateNarrative');
        $method->setAccessible(true);

        $method->invoke($job, $this->wellFormedNarrative(
            'Your story suggests a pull toward caring for people directly, and it is worth testing.'
        ));

        $this->assertTrue(true);
    }

    /**
     * The retry must be told what was actually wrong. Handing a model a note
     * about markdown headers when it claimed to speak for God produces a
     * well-formatted second draft with the same violation in it.
     */
    #[Test]
    public function its_timeout_exceeds_the_database_queue_retry_after_default(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $this->assertGreaterThan(
            $job->timeout,
            config('queue.connections.database.retry_after'),
            'queue retry_after must exceed job timeout or workers may duplicate ai-analysis runs.',
        );
    }

    #[Test]
    public function a_violation_carries_the_findings_needed_to_write_a_repair(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateNarrative');
        $method->setAccessible(true);

        try {
            $method->invoke($job, $this->wellFormedNarrative('You were born for this destiny.'));
            $this->fail('Expected a RedTeamViolation.');
        } catch (RedTeamViolation $violation) {
            $this->assertContains('determinism', array_column($violation->findings, 'rule'));
            $this->assertStringContainsString('destiny', RedTeamLint::repairInstruction($violation->findings));
        }
    }
}
