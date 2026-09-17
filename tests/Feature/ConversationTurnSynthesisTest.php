<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ConversationSession;
use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Tests\TestCase;

/**
 * A model can judge an answer sufficient and still omit the synthesis. Small
 * local models do this routinely, and the hosted models do it occasionally.
 * The stored answer must never end up empty: the student's own words are the
 * evidence the whole engine reads from.
 */
class ConversationTurnSynthesisTest extends TestCase
{
    use RefreshDatabase;

    protected string $transcript = 'There was a girl crying in the bathroom and I just sat with her.';

    protected function startedSession(): ConversationSession
    {
        $category = QuestionCategory::create([
            'name' => 'Service',
            'slug' => 'service',
            'sort_order' => 1,
        ]);

        Question::create([
            'category_id' => $category->id,
            'question_text' => 'Describe a time you helped someone.',
            'sort_order' => 1,
            'is_beta' => false,
        ]);

        $assessment = Assessment::create([
            'mode' => 'audio',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        return ConversationSession::create([
            'assessment_id' => $assessment->id,
            'status' => 'active',
            'locale' => 'en',
            'speech_locale' => 'en',
            'current_question_index' => 0,
        ]);
    }

    protected function fakeStructured(array $structured): void
    {
        Prism::fake([
            StructuredResponseFake::make()->withStructured($structured),
        ]);
    }

    public function test_it_stores_the_synthesized_answer_when_the_model_provides_one(): void
    {
        $session = $this->startedSession();

        $this->fakeStructured([
            'is_sufficient' => true,
            'follow_up_question' => null,
            'synthesized_answer' => 'The student sat with a classmate who was crying.',
            'reasoning' => 'Specific and personal.',
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => $this->transcript,
        ])->assertOk();

        $this->assertSame(
            'The student sat with a classmate who was crying.',
            Answer::where('assessment_id', $session->assessment_id)->value('response_text'),
        );
    }

    public function test_it_falls_back_to_the_transcript_when_the_synthesis_is_null(): void
    {
        $session = $this->startedSession();

        $this->fakeStructured([
            'is_sufficient' => true,
            'follow_up_question' => null,
            'synthesized_answer' => null,
            'reasoning' => 'Sufficient.',
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => $this->transcript,
        ])->assertOk();

        $this->assertSame(
            $this->transcript,
            Answer::where('assessment_id', $session->assessment_id)->value('response_text'),
        );
    }

    public function test_it_falls_back_to_the_transcript_when_the_synthesis_is_blank(): void
    {
        $session = $this->startedSession();

        $this->fakeStructured([
            'is_sufficient' => true,
            'follow_up_question' => null,
            'synthesized_answer' => '   ',
            'reasoning' => 'Sufficient.',
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => $this->transcript,
        ])->assertOk();

        $this->assertSame(
            $this->transcript,
            Answer::where('assessment_id', $session->assessment_id)->value('response_text'),
        );
    }

    public function test_it_never_stores_an_empty_answer_when_the_model_omits_the_field_entirely(): void
    {
        $session = $this->startedSession();

        $this->fakeStructured([
            'is_sufficient' => true,
            'reasoning' => 'Sufficient.',
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => $this->transcript,
        ])->assertOk();

        $answer = Answer::where('assessment_id', $session->assessment_id)->first();

        $this->assertNotNull($answer);
        $this->assertNotEmpty($answer->response_text);
    }

    public function test_an_insufficient_response_asks_a_follow_up_and_stores_no_answer(): void
    {
        $session = $this->startedSession();

        $this->fakeStructured([
            'is_sufficient' => false,
            'follow_up_question' => 'What made that moment stay with you?',
            'synthesized_answer' => null,
            'reasoning' => 'Too thin.',
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => $this->transcript,
        ])->assertOk();

        $this->assertSame(0, Answer::where('assessment_id', $session->assessment_id)->count());
    }
}
