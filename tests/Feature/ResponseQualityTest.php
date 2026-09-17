<?php

namespace Tests\Feature;

use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\SignalExtraction;
use App\Support\ConfidenceCalculator;
use App\Support\ResponseQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blueprint 11 sets the hard constraint on this feature: answer quality is
 * "specificity, reflection, coherence, behavioral grounding — NOT writing or
 * speaking skill." Every convenient proxy for quality is a literacy proxy, so
 * the score is built from verified Layer 4 signals instead.
 */
class ResponseQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function assessment(): Assessment
    {
        return Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);
    }

    protected function answer(Assessment $assessment, string $text, int $order = 1): Answer
    {
        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'service'],
            ['name' => 'Service', 'sort_order' => 1],
        );

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => "Question {$order}?",
            'sort_order' => $order,
            'is_beta' => false,
        ]);

        return Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => $text,
        ]);
    }

    protected function signal(Answer $answer, SignalType $type, SignalTrack $track, string $verbatim): void
    {
        $answer->assessment->signalExtractions()->create([
            'answer_id' => $answer->id,
            'type' => $type,
            'track' => $track,
            'content' => 'A signal.',
            'verbatim' => $verbatim,
            'sort_order' => 0,
        ]);
    }

    /**
     * The central guarantee. Two answers describing the same experience with
     * the same evidence must score the same, whatever the writing looks like.
     */
    public function test_polished_and_unpolished_answers_with_the_same_evidence_score_the_same(): void
    {
        $polished = ResponseQuality::bands(
            'I sat with her until she stopped crying, because I could not bear to leave her alone.',
            collect([
                $this->fakeSignal(SignalTrack::Demonstrated),
                $this->fakeSignal(SignalTrack::Demonstrated),
            ]),
        );

        $unpolished = ResponseQuality::bands(
            'i sat with her til she stoped cryin cuz i couldnt just leave her there by herself alone',
            collect([
                $this->fakeSignal(SignalTrack::Demonstrated),
                $this->fakeSignal(SignalTrack::Demonstrated),
            ]),
        );

        $this->assertSame($polished['density'], $unpolished['density']);
        $this->assertSame($polished['grounding'], $unpolished['grounding']);
        $this->assertSame($polished['total'], $unpolished['total']);
    }

    /**
     * A signal only needs its track to be scored; building one in memory
     * keeps these cases about the scoring rule rather than about persistence.
     */
    protected function fakeSignal(SignalTrack $track): SignalExtraction
    {
        $signal = new SignalExtraction;
        $signal->track = $track;

        return $signal;
    }

    /**
     * Length alone must not buy a high score. A long answer that yielded no
     * interpretable signal is not strong evidence, it is a long answer.
     */
    public function test_length_alone_cannot_produce_a_high_score(): void
    {
        $bands = ResponseQuality::bands(str_repeat('something and then something else ', 40), collect());

        $this->assertSame(ResponseQuality::SUBSTANCE_MAX, $bands['substance']);
        $this->assertSame(0, $bands['density']);
        $this->assertSame(ResponseQuality::SUBSTANCE_MAX, $bands['total']);
        $this->assertLessThan(ConfidenceCalculator::SUBSTANTIVE_QUALITY, $bands['total']);
    }

    /**
     * Blueprint 10.3: what someone has done outweighs what they want. A short
     * answer carrying demonstrated evidence must beat a long one carrying only
     * aspiration.
     */
    public function test_a_short_demonstrated_answer_outscores_a_long_aspirational_one(): void
    {
        $demonstrated = ResponseQuality::bands('I sat with her until she stopped.', collect([
            $this->fakeSignal(SignalTrack::Demonstrated),
            $this->fakeSignal(SignalTrack::Demonstrated),
        ]));

        $aspirational = ResponseQuality::bands(
            str_repeat('I really want to help people someday and make a difference somehow. ', 6),
            collect([$this->fakeSignal(SignalTrack::Aspiration)]),
        );

        $this->assertGreaterThan($aspirational['total'], $demonstrated['total']);
    }

    public function test_an_empty_answer_scores_zero_across_every_band(): void
    {
        $this->assertSame(
            ['substance' => 0, 'density' => 0, 'grounding' => 0, 'total' => 0],
            ResponseQuality::bands('   ', collect()),
        );
    }

    /**
     * Layer 4 is allowed to fail without taking the analysis with it. If it
     * did, the signal bands carry no information — scoring them zero would
     * report a rich answer as empty and wrongly collapse confidence.
     */
    public function test_it_falls_back_to_substance_when_signal_extraction_never_ran(): void
    {
        $text = str_repeat('a specific thing that happened to me last spring ', 8);

        $withoutExtraction = ResponseQuality::bands($text, collect(), extractionRan: false);
        $withExtraction = ResponseQuality::bands($text, collect(), extractionRan: true);

        $this->assertSame(100, $withoutExtraction['total']);
        $this->assertSame(ResponseQuality::SUBSTANCE_MAX, $withExtraction['total']);
    }

    public function test_it_persists_a_score_and_its_breakdown_for_every_answer(): void
    {
        $assessment = $this->assessment();
        $answer = $this->answer($assessment, 'I sat with her until she stopped crying and I stayed the whole time.');
        $this->signal($answer, SignalType::Burden, SignalTrack::Demonstrated, 'I sat with her');

        (new ResponseQuality)->scoreAssessment($assessment);

        $answer->refresh();

        $this->assertNotNull($answer->response_quality_score);
        $this->assertGreaterThan(0, $answer->response_quality_score);
        $this->assertSame(
            ['substance', 'density', 'grounding', 'total'],
            array_keys($answer->response_quality_bands),
        );
        $this->assertSame($answer->response_quality_score, $answer->response_quality_bands['total']);
    }

    public function test_scores_stay_within_zero_and_one_hundred(): void
    {
        $bands = ResponseQuality::bands(str_repeat('word ', 500), collect(array_map(
            fn () => $this->fakeSignal(SignalTrack::Demonstrated),
            range(1, 40),
        )));

        $this->assertSame(100, $bands['total']);
        $this->assertGreaterThanOrEqual(0, $bands['total']);
    }

    /**
     * The point of 0.6 is that it gates low-confidence mode. A body of thin
     * answers must cap confidence no matter how the categories scored.
     */
    public function test_thin_answers_cap_confidence_through_the_quality_ceiling(): void
    {
        $categoryScores = [
            ['category' => 'Healing & Care', 'score' => 95],
            ['category' => 'Teaching & Formation', 'score' => 40],
        ];

        $thin = ConfidenceCalculator::overall($categoryScores, [], [10, 12, 8, 15]);
        $rich = ConfidenceCalculator::overall($categoryScores, [], [80, 75, 90, 85, 78, 82, 88, 76]);

        $this->assertTrue($thin->isLowConfidence());
        $this->assertFalse($rich->isLowConfidence());
    }

    /**
     * Quality scores are the better input, but they are optional. Assessments
     * analysed before 0.6 must keep working off word counts.
     */
    public function test_confidence_still_works_from_words_when_no_scores_are_available(): void
    {
        $categoryScores = [['category' => 'Healing & Care', 'score' => 95]];

        $fromWords = ConfidenceCalculator::evidenceCeiling(array_fill(0, 8, str_repeat('a real sentence about something specific ', 8)));

        $this->assertFalse($fromWords->isLowConfidence());
        $this->assertNotEmpty(ConfidenceCalculator::explain($categoryScores, ['a short answer here please'])['missing_evidence']);
    }

    /**
     * The explanation must be measured against whatever set the ceiling, or
     * the system can report a level its own rationale contradicts.
     */
    public function test_the_missing_evidence_explanation_follows_the_quality_scores(): void
    {
        $result = ConfidenceCalculator::explain(
            [['category' => 'Healing & Care', 'score' => 90]],
            [],
            [5, 8, 4],
        );

        $this->assertTrue($result['level']->isLowConfidence());
        $this->assertNotEmpty($result['missing_evidence']);
        $this->assertStringNotContainsString('not enough information to help', strtolower($result['rationale']));
    }
}
