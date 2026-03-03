<?php

namespace Tests\Feature;

use App\Ai\Agents\NarrativeSynthesis;
use App\Ai\Agents\VocationalAnalysis;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\VocationalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_completing_assessment_dispatches_analysis_job(): void
    {
        Queue::fake();

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $questions = Question::limit(3)->get();
        foreach ($questions as $question) {
            $assessment->answers()->create([
                'question_id' => $question->id,
                'response_text' => 'Test response for question: '.$question->question_text,
            ]);
        }

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/complete",
            [],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertOk();
        $response->assertJson(['status' => 'analyzing']);

        Queue::assertPushed(AnalyzeAssessmentJob::class, function ($job) use ($assessment) {
            return $job->assessment->id === $assessment->id;
        });
    }

    public function test_completing_assessment_with_no_answers_returns_422(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/complete",
            [],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertStatus(422);
    }

    public function test_results_return_202_while_analyzing(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'analyzing',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/assessments/{$assessment->id}/results",
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertStatus(202);
        $response->assertJson(['status' => 'analyzing']);
    }

    public function test_results_return_profile_when_completed(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => 'You are drawn to creative problem-solving.',
            'vocational_orientation' => 'Your orientation blends innovation with care.',
            'primary_pathways' => ['Architecture with community focus', 'Urban planning'],
            'specific_considerations' => 'Your leadership instincts are strong.',
            'next_steps' => ['Explore architecture programs', 'Find a mentor in the field'],
            'ministry_integration' => 'Your work in design is itself an act of service.',
            'primary_domain' => 'Creating & Building',
            'mode_of_work' => 'Entrepreneurial',
            'secondary_orientation' => 'Leadership & Management',
            'category_scores' => [
                ['category' => 'Creating & Building', 'score' => 90, 'rationale' => 'Strong design affinity'],
            ],
            'ai_analysis_raw' => [],
        ]);

        $response = $this->getJson(
            "/api/v1/assessments/{$assessment->id}/results",
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'opening_synthesis',
                'vocational_orientation',
                'primary_domain',
                'mode_of_work',
                'secondary_orientation',
                'created_at',
            ],
        ]);
    }

    public function test_unauthorized_access_returns_403(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/assessments/{$assessment->id}/results",
            ['X-Guest-Token' => 'wrong-token']
        );

        $response->assertStatus(403);
    }

    public function test_vocational_analysis_agent_builds_prompt_from_assessment(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $question = Question::first();
        $assessment->answers()->create([
            'question_id' => $question->id,
            'response_text' => 'I love helping people solve complex technical problems.',
        ]);

        $agent = new VocationalAnalysis($assessment);
        $prompt = $agent->buildPrompt();

        $this->assertStringContainsString('I love helping people solve complex technical problems', $prompt);
        $this->assertStringContainsString('Assessment Responses', $prompt);
    }

    public function test_results_show_failed_status_returns_500(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'failed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/assessments/{$assessment->id}/results",
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertStatus(500);
        $response->assertJson(['status' => 'failed']);
    }
}
