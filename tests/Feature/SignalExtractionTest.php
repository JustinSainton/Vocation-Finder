<?php

namespace Tests\Feature;

use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Support\SignalExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Tests\TestCase;

/**
 * Layer 4 exists to make one of the brain's invariants mechanical: it
 * "surfaces what the student already said; it does not write what they would
 * have said." Because every signal must carry the span it came from, a quote
 * the student never said is a substring miss, not a judgement call.
 */
class SignalExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected string $response = 'There was a girl crying in the bathroom and I just sat with her until she stopped. I want to be a nurse someday.';

    protected function assessmentWithAnswer(?string $text = null): Assessment
    {
        $category = QuestionCategory::create(['name' => 'Service', 'slug' => 'service', 'sort_order' => 1]);

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Describe a time you helped someone.',
            'sort_order' => 1,
            'is_beta' => false,
        ]);

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => $text ?? $this->response,
        ]);

        return $assessment;
    }

    protected function fakeSignals(array $signals): void
    {
        Prism::fake([
            StructuredResponseFake::make()->withStructured(['signals' => $signals]),
        ]);
    }

    protected function signal(array $overrides = []): array
    {
        return array_merge([
            'answer_id' => '',
            'type' => 'burden',
            'track' => 'demonstrated',
            'content' => 'Moved toward someone in distress.',
            'verbatim' => 'I just sat with her until she stopped',
            'constraint_nature' => 'not_applicable',
        ], $overrides);
    }

    public function test_it_stores_a_signal_whose_verbatim_appears_in_the_answer(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([$this->signal(['answer_id' => $answerId])]);

        $signals = (new SignalExtractor)->extract($assessment);

        $this->assertCount(1, $signals);
        $this->assertSame(SignalType::Burden, $signals->first()->type);
        $this->assertSame(SignalTrack::Demonstrated, $signals->first()->track);
        $this->assertSame('I just sat with her until she stopped', $signals->first()->verbatim);
    }

    public function test_it_discards_a_verbatim_the_respondent_never_wrote(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal(['answer_id' => $answerId]),
            $this->signal([
                'answer_id' => $answerId,
                'content' => 'Feels called to medicine.',
                'verbatim' => 'I have always known I was meant to heal people',
            ]),
        ]);

        $signals = (new SignalExtractor)->extract($assessment);

        $this->assertCount(1, $signals);
        $this->assertSame('I just sat with her until she stopped', $signals->first()->verbatim);
    }

    /**
     * A paraphrase is the failure mode this layer is built against: it reads
     * as faithful, uses the student's subject matter, and is still the model's
     * sentence rather than theirs.
     */
    public function test_it_discards_a_paraphrase_of_something_the_respondent_did_write(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal([
                'answer_id' => $answerId,
                'verbatim' => 'I sat with a girl who was crying until she calmed down',
            ]),
        ]);

        $this->assertCount(0, (new SignalExtractor)->extract($assessment));
    }

    public function test_it_accepts_a_span_that_differs_only_by_smart_punctuation_or_whitespace(): void
    {
        $assessment = $this->assessmentWithAnswer('I couldn\'t stand it, so I stayed.');
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal([
                'answer_id' => $answerId,
                'verbatim' => "I couldn\u{2019}t stand it,  so I stayed.",
            ]),
        ]);

        $this->assertCount(1, (new SignalExtractor)->extract($assessment));
    }

    public function test_it_discards_a_signal_attributed_to_an_answer_that_was_never_sent(): void
    {
        $assessment = $this->assessmentWithAnswer();

        $this->fakeSignals([$this->signal(['answer_id' => (string) Str::uuid()])]);

        $this->assertCount(0, (new SignalExtractor)->extract($assessment));
    }

    public function test_it_discards_a_signal_with_an_unknown_type_or_track(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal(['answer_id' => $answerId, 'type' => 'vibe']),
            $this->signal(['answer_id' => $answerId, 'track' => 'maybe']),
        ]);

        $this->assertCount(0, (new SignalExtractor)->extract($assessment));
    }

    /**
     * A constraint's nature decides whether a pathway is closed, delayed, or
     * merely inconvenient, so it is recorded only when it is usable — and it
     * is never claimed by a signal that is not a constraint.
     */
    public function test_it_records_constraint_nature_only_for_constraints(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal([
                'answer_id' => $answerId,
                'type' => 'constraint',
                'constraint_nature' => 'temporary',
            ]),
            $this->signal([
                'answer_id' => $answerId,
                'type' => 'desire',
                'verbatim' => 'I want to be a nurse someday',
                'track' => 'aspiration',
                'constraint_nature' => 'fixed',
            ]),
        ]);

        $signals = (new SignalExtractor)->extract($assessment);

        $this->assertSame('temporary', $signals->firstWhere('type', SignalType::Constraint)->constraint_nature);
        $this->assertNull($signals->firstWhere('type', SignalType::Desire)->constraint_nature);
    }

    public function test_it_replaces_prior_signals_so_reanalysis_is_idempotent(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([$this->signal(['answer_id' => $answerId])]);
        (new SignalExtractor)->extract($assessment);

        $this->fakeSignals([$this->signal(['answer_id' => $answerId])]);
        (new SignalExtractor)->extract($assessment);

        $this->assertSame(1, $assessment->signalExtractions()->count());
    }

    public function test_the_aspiration_and_demonstrated_tracks_are_queryable_separately(): void
    {
        $assessment = $this->assessmentWithAnswer();
        $answerId = (string) $assessment->answers()->first()->id;

        $this->fakeSignals([
            $this->signal(['answer_id' => $answerId]),
            $this->signal([
                'answer_id' => $answerId,
                'type' => 'desire',
                'track' => 'aspiration',
                'verbatim' => 'I want to be a nurse someday',
            ]),
        ]);

        (new SignalExtractor)->extract($assessment);

        $this->assertSame(1, $assessment->signalExtractions()->demonstrated()->count());
        $this->assertSame(1, $assessment->signalExtractions()->aspirational()->count());
    }

    public function test_it_does_nothing_when_there_are_no_substantive_answers(): void
    {
        $assessment = $this->assessmentWithAnswer('   ');

        $this->assertCount(0, (new SignalExtractor)->extract($assessment));
    }

    /**
     * Blueprint 10.3: only signals evidencing what someone has practised,
     * endured, or been trusted with can carry demonstrated fit. Wanting a
     * thing is never evidence of doing it.
     */
    public function test_only_practised_signal_types_can_evidence_demonstrated_fit(): void
    {
        $this->assertTrue(SignalType::Skill->canEvidenceDemonstratedFit());
        $this->assertTrue(SignalType::Burden->canEvidenceDemonstratedFit());
        $this->assertFalse(SignalType::Desire->canEvidenceDemonstratedFit());
        $this->assertFalse(SignalType::Interest->canEvidenceDemonstratedFit());
    }

    public function test_burdens_and_skills_outweigh_desires_and_interests(): void
    {
        $this->assertGreaterThan(SignalType::Desire->weight(), SignalType::Burden->weight());
        $this->assertGreaterThan(SignalType::Interest->weight(), SignalType::Skill->weight());
    }
}
