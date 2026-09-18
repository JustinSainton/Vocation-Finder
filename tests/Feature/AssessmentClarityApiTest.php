<?php

namespace Tests\Feature;

use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\VocationalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentClarityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function assessment(): Assessment
    {
        return Assessment::create([
            'user_id' => null,
            'mode' => 'written',
            'status' => 'in_progress',
            'started_at' => now(),
            'guest_token' => Str::random(64),
        ]);
    }

    protected function headers(Assessment $assessment): array
    {
        return ['X-Guest-Token' => $assessment->guest_token];
    }

    #[Test]
    public function guest_records_a_before_reading_in_words(): void
    {
        $assessment = $this->assessment();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'vague_sense'],
            $this->headers($assessment),
        );

        $response->assertCreated();
        $this->assertDatabaseHas('clarity_checks', [
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before->value,
            'standing' => ClarityStanding::VagueSense->value,
        ]);
    }

    #[Test]
    public function unknown_standing_is_refused(): void
    {
        $assessment = $this->assessment();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'seven'],
            $this->headers($assessment),
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function before_reading_after_answers_is_refused(): void
    {
        $assessment = $this->assessment();
        Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => Question::create([
                'category_id' => QuestionCategory::firstOrCreate(
                    ['slug' => 'service'],
                    ['name' => 'Service', 'sort_order' => 1],
                )->id,
                'question_text' => 'What do people come to you for?',
                'sort_order' => 1,
            ])->id,
            'response_text' => 'I like fixing things.',
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'no_idea'],
            $this->headers($assessment),
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function after_reading_without_a_portrait_is_refused(): void
    {
        $assessment = $this->assessment();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'after', 'standing' => 'few_options'],
            $this->headers($assessment),
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function after_reading_with_a_portrait_is_recorded(): void
    {
        $assessment = $this->assessment();
        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'user_id' => $assessment->user_id,
            'opening_synthesis' => 'You keep going back to the same kind of problem.',
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'after', 'standing' => 'few_options'],
            $this->headers($assessment),
        );

        $response->assertCreated();
    }

    #[Test]
    public function first_reading_stands_on_repeat(): void
    {
        $assessment = $this->assessment();
        $headers = $this->headers($assessment);

        $first = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'no_idea'],
            $headers,
        );
        $second = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'know_what_to_try'],
            $headers,
        );

        $second->assertCreated();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseHas('clarity_checks', [
            'assessment_id' => $assessment->id,
            'moment' => ClarityMoment::Before->value,
            'standing' => ClarityStanding::NoIdea->value,
        ]);
    }

    #[Test]
    public function stranger_without_the_token_is_refused(): void
    {
        $assessment = $this->assessment();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/clarity",
            ['moment' => 'before', 'standing' => 'no_idea'],
            ['X-Guest-Token' => 'wrong'],
        );

        $response->assertForbidden();
    }
}
