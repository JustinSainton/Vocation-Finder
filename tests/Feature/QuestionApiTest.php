<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_questions_endpoint_returns_all_questions(): void
    {
        $response = $this->getJson('/api/v1/questions');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'question_text',
                    'sort_order',
                    'category_name',
                ],
            ],
        ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_full_assessment_is_served_by_default(): void
    {
        config(['vocation.beta.questions_enabled' => false]);

        $response = $this->getJson('/api/v1/questions');

        $response->assertOk();

        $fullCount = Question::where('is_beta', false)->count();
        $betaCount = Question::where('is_beta', true)->count();

        $this->assertGreaterThan($betaCount, $fullCount, 'Seed data should contain a larger full question set than the beta set.');
        $this->assertCount($fullCount, $response->json('data'));
    }

    public function test_beta_flag_serves_the_short_question_set(): void
    {
        config(['vocation.beta.questions_enabled' => true]);

        $response = $this->getJson('/api/v1/questions');

        $response->assertOk();

        $betaCount = Question::where('is_beta', true)->count();

        $this->assertCount($betaCount, $response->json('data'));
        $this->assertLessThan(Question::where('is_beta', false)->count(), $betaCount);
    }

    public function test_questions_are_sorted_by_sort_order(): void
    {
        $response = $this->getJson('/api/v1/questions');

        $questions = $response->json('data');
        $sortOrders = array_column($questions, 'sort_order');

        $sorted = $sortOrders;
        sort($sorted);
        $this->assertEquals($sorted, $sortOrders);
    }

    public function test_questions_include_follow_up_prompts(): void
    {
        $response = $this->getJson('/api/v1/questions');

        $questions = $response->json('data');
        foreach ($questions as $question) {
            $this->assertArrayHasKey('follow_up_prompts', $question);
            $this->assertIsArray($question['follow_up_prompts']);
        }
    }
}
