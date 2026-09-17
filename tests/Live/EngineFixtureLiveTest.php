<?php

namespace Tests\Live;

use App\Enums\ConfidenceLevel;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\SignalExtraction;
use App\Support\NarrativeLanguage;
use App\Support\RedTeamLint;
use App\Support\SignalExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\EngineFixtureTest;
use Tests\TestCase;

/**
 * The golden corpus, driven through a real model.
 *
 * {@see EngineFixtureTest} re-derives our deterministic layers
 * from recorded output and catches *us* drifting. This catches the *model*
 * drifting underneath us, which no amount of recorded output can: a new
 * checkpoint that stops citing signals, or starts overstating, changes
 * nothing in the recorded fixtures and everything in production.
 *
 * Costs real money, so it is excluded from the default suite. Run it when a
 * model version changes, before a cohort, and when a prompt is edited.
 *
 * It asserts only what must hold for *every* student, not the fixture's exact
 * recorded values. A model that reaches the same conclusion by a different
 * route has not regressed; one that invents a quote has.
 */
#[Group('live')]
class EngineFixtureLiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function fixtures(): array
    {
        $cases = [];

        foreach (glob(dirname(__DIR__).'/Fixtures/Engine/*.json') as $path) {
            $fixture = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

            // The refusal case records a narrative to lint, not answers to run.
            if ($fixture['expect']['red_team_clean'] ?? true) {
                $cases[$fixture['name']] = [$fixture];
            }
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function the_engine_still_holds_on_this_case(array $fixture): void
    {
        $this->seed();

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
            // A Spanish assessment run without its locale is not the case the
            // fixture describes — the engine would answer a Spanish student
            // in English and the run would prove nothing about either.
            'locale' => $fixture['locale'] ?? 'en-US',
        ]);

        $questions = Question::orderBy('sort_order')->limit(count($fixture['answers']))->get();

        foreach ($fixture['answers'] as $index => $text) {
            Answer::create([
                'assessment_id' => $assessment->id,
                'question_id' => $questions[$index % $questions->count()]->id,
                'response_text' => $text,
            ]);
        }

        // 0. The assessment has to contain the assessment.
        //
        //    It did not, for as long as this file has existed: the answers
        //    were written to `answer_text`, which is not a column and not
        //    fillable, so Eloquent dropped every one of them silently and the
        //    engine spent four hundred seconds interpreting seven blank
        //    responses. Nothing failed — a model given nothing returns
        //    nothing, and every assertion below tolerates nothing.
        $stored = $assessment->answers()->pluck('response_text')->all();

        sort($stored);
        $expected = $fixture['answers'];
        sort($expected);

        $this->assertSame($expected, $stored, "The assessment was never populated ({$fixture['name']}).");

        (new AnalyzeAssessmentJob($assessment))->handle();

        $profile = $assessment->fresh()->vocationalProfile;

        $this->assertNotNull($profile, "The engine produced nothing for {$fixture['name']}.");

        fwrite(STDERR, PHP_EOL."── {$fixture['name']}".PHP_EOL);
        fwrite(STDERR, '   confidence: '.$profile->confidence_level?->value.PHP_EOL);
        fwrite(STDERR, '   standing:   '.($profile->evidence_gap['standing'] ?? 'none').PHP_EOL);
        fwrite(STDERR, '   signals:    '.SignalExtraction::where('assessment_id', $assessment->id)->count().PHP_EOL);

        /*
         | A run here costs seven minutes, so it has to come back with a
         | diagnosis rather than a verdict. The leading category's evidence is
         | where a weak model fails visibly: `DualTrack` only counts citations
         | that name a signal (`S1`, `S2`, …), so a model that cites the
         | question text, or the answer text, or nothing at all, lands on
         | `unevidenced` with every other layer working perfectly.
         */
        $leading = collect($profile->category_scores ?? [])
            ->sortByDesc(fn (array $row) => $row['score'] ?? 0)
            ->first() ?? [];

        fwrite(STDERR, '   leading:    '.($leading['category'] ?? 'none').PHP_EOL);
        fwrite(STDERR, '   cited:      '.json_encode($leading['evidence'] ?? [], JSON_UNESCAPED_UNICODE).PHP_EOL);
        fwrite(STDERR, '   rationale:  '.$profile->confidence_rationale.PHP_EOL);

        // 1. Provenance. The only claim with no acceptable failure rate.
        foreach (SignalExtraction::where('assessment_id', $assessment->id)->get() as $signal) {
            $this->assertTrue(
                SignalExtractor::spanAppearsIn($signal->verbatim, (string) $signal->answer?->response_text),
                "The model invented a quote on {$fixture['name']}: \"{$signal->verbatim}\"",
            );
        }

        // 2. The narrative that shipped must survive the lint it was linted by.
        $prose = collect([
            $profile->opening_synthesis,
            $profile->vocational_orientation,
            $profile->specific_considerations,
        ])->filter()->implode("\n\n");

        $this->assertSame([], RedTeamLint::blocking(RedTeamLint::inspect($prose)));

        // 2b. And it must be in the language the student wrote in. This
        //     failure is silent and total: a Spanish student receives a
        //     fluent, correct, entirely English portrait, every other
        //     assertion here passes, and the run reports success.
        $this->assertSame(
            NarrativeLanguage::expectedFor($fixture['locale'] ?? 'en-US'),
            NarrativeLanguage::of($prose),
            "The engine answered {$fixture['name']} in the wrong language.",
        );

        // 3. A thin assessment must not become a confident one, whatever the
        //    model does with it. This is the ceiling doing its job, and it is
        //    the assertion most likely to catch a newer, more eager model.
        if (($fixture['expect']['confidence'] ?? null) === ConfidenceLevel::InsufficientEvidence->value) {
            $this->assertFalse(
                $profile->confidence_level?->permitsConclusion(),
                "The engine named a direction from an assessment with nothing in it ({$fixture['name']}).",
            );
        }

        // 4. Wanting it is still not having done it.
        if (($fixture['expect']['evidence_standing'] ?? null) === 'aspiration_only') {
            $this->assertFalse(
                $profile->evidence_gap['supports_naming_a_direction'] ?? true,
                "The engine treated an untested ambition as demonstrated ({$fixture['name']}).",
            );
        }

        // 5. The floor, and the reason this file needed one.
        //
        //    Assertions 1, 3 and 4 are all ceilings: don't invent a quote,
        //    don't overstate, don't mistake wanting for doing. A model that
        //    returns *nothing at all* satisfies every one of them — assertion
        //    1 iterates over an empty set, and 3 and 4 only fire on fixtures
        //    that expect little. The first local model run through this
        //    harness extracted zero signals from a seven-answer assessment,
        //    collapsed a `demonstrated`/`moderate` case to
        //    `unevidenced`/`insufficient_evidence`, and the run reported
        //    success with three assertions passed.
        //
        //    Where the corpus says the student demonstrated something, the
        //    engine has to find it. That is not a recorded value; it is the
        //    minimum a model has to do to be worth running.
        if (($fixture['expect']['evidence_standing'] ?? null) === 'demonstrated') {
            $this->assertGreaterThan(
                0,
                SignalExtraction::where('assessment_id', $assessment->id)->count(),
                "The engine read a whole assessment and quoted nothing back ({$fixture['name']}).",
            );

            $this->assertTrue(
                $profile->evidence_gap['supports_naming_a_direction'] ?? false,
                "The engine found no demonstrated evidence where the corpus records it ({$fixture['name']}).",
            );
        }
    }
}
