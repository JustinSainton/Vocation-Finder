<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionCategory;
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
