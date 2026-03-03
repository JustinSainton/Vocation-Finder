<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_can_create_written_assessment(): void
    {
        $response = $this->postJson('/api/v1/assessments', [
            'mode' => 'written',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'mode', 'status', 'guest_token']);
        $this->assertEquals('written', $response->json('mode'));
        $this->assertEquals('in_progress', $response->json('status'));
        $this->assertNotNull($response->json('guest_token'));
    }

    public function test_guest_can_create_conversation_assessment(): void
    {
        $response = $this->postJson('/api/v1/assessments', [
            'mode' => 'conversation',
        ]);

        $response->assertCreated();
        $this->assertEquals('conversation', $response->json('mode'));
    }

    public function test_invalid_mode_returns_422(): void
    {
        $response = $this->postJson('/api/v1/assessments', [
            'mode' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_can_save_answer(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $question = Question::first();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            [
                'question_id' => $question->id,
                'response_text' => 'I find meaning in creative work.',
            ],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertOk();
        $response->assertJsonStructure(['id']);

        $this->assertDatabaseHas('answers', [
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_saving_answer_to_same_question_updates_existing(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $question = Question::first();

        // First answer
        $this->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            [
                'question_id' => $question->id,
                'response_text' => 'Original answer',
            ],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        // Updated answer
        $this->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            [
                'question_id' => $question->id,
                'response_text' => 'Updated answer',
            ],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $this->assertEquals(
            1,
            $assessment->answers()->where('question_id', $question->id)->count()
        );
        $this->assertEquals(
            'Updated answer',
            $assessment->answers()->where('question_id', $question->id)->first()->response_text
        );
    }

    public function test_unauthorized_save_answer_returns_403(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $question = Question::first();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/answers",
            [
                'question_id' => $question->id,
                'response_text' => 'Sneaky answer',
            ],
            ['X-Guest-Token' => 'wrong-token']
        );

        $response->assertStatus(403);
    }

    public function test_show_assessment_with_answers(): void
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
            'response_text' => 'Test answer',
        ]);

        $response = $this->getJson(
            "/api/v1/assessments/{$assessment->id}",
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'mode', 'status'],
        ]);
    }
}
