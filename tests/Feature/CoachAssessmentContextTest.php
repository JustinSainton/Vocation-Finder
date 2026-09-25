<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Tools\GetAssessmentResponsesTool;
use App\Enums\ConfidenceLevel;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ParentConsent;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Support\CoachAssessmentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachAssessmentContextTest extends TestCase
{
    use RefreshDatabase;

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

    protected function portraitAssessment(User $user): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Describe a time you helped someone.',
            'sort_order' => 1,
            'is_beta' => false,
        ]);

        Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => 'I just sat with her until she stopped crying.',
        ]);

        $assessment->vocationalProfile()->create([
            'opening_synthesis' => 'You come alive when someone needs you to stay.',
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'confidence_level' => ConfidenceLevel::Moderate,
        ]);

        return $assessment;
    }

    #[Test]
    public function the_context_payload_includes_profile_and_every_answer(): void
    {
        $student = $this->student();
        $this->portraitAssessment($student);

        $payload = (new CoachAssessmentContext)->payload($student);

        $this->assertNotNull($payload);
        $this->assertSame('caring for people directly', $payload['profile']['primary_domain']);
        $this->assertCount(1, $payload['responses']);
        $this->assertSame('I just sat with her until she stopped crying.', $payload['responses'][0]['response']);
    }

    #[Test]
    public function the_responses_tool_returns_the_same_answers(): void
    {
        $student = $this->student();
        $this->portraitAssessment($student);

        $payload = json_decode((new GetAssessmentResponsesTool($student))->handle(new Request([])), true);

        $this->assertTrue($payload['has_responses']);
        $this->assertSame('I just sat with her until she stopped crying.', $payload['responses'][0]['response']);
    }

    #[Test]
    public function the_first_exchange_instructions_include_assessment_context(): void
    {
        $student = $this->student();
        $this->portraitAssessment($student);

        $instructions = (string) (new PathwayCoachAgent($student))->instructions();

        $this->assertStringContainsString('Assessment context (already loaded)', $instructions);
        $this->assertStringContainsString('I just sat with her until she stopped crying.', $instructions);
        $this->assertStringContainsString('caring for people directly', $instructions);
    }

    #[Test]
    public function the_opening_instruction_includes_assessment_context(): void
    {
        $student = $this->student();
        $this->portraitAssessment($student);

        $method = new \ReflectionMethod(PathwayCoachAgent::class, 'openingInstruction');
        $method->setAccessible(true);

        $instruction = (string) $method->invoke(new PathwayCoachAgent($student), 'first');

        $this->assertStringContainsString('Assessment context (already loaded)', $instruction);
        $this->assertStringContainsString('I just sat with her until she stopped crying.', $instruction);
    }
}
