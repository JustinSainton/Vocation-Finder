<?php

namespace Tests\Feature;

use App\Enums\ConfidenceLevel;
use App\Enums\EvidenceStanding;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\SignalExtraction;
use App\Support\ConfidenceCalculator;
use App\Support\ConversationLocale;
use App\Support\DualTrack;
use App\Support\RedTeamLint;
use App\Support\ResponseQuality;
use App\Support\SignalExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 0.9 — the test-case library, as a golden corpus.
 *
 * Every other test in this suite checks a rule against an input written to
 * exercise that rule. These check the whole deterministic half of the engine
 * against whole assessments that either came from, or are shaped like, real
 * students — including the one the engine was first proven against live.
 *
 * ## Why it runs without a model
 *
 * Drift defence that costs money and needs an API key runs occasionally;
 * drift defence that runs on every commit is the one that actually catches
 * anything. So the model's output is recorded in each fixture and the layers
 * that are *ours* — provenance, the red-team lint, response quality,
 * confidence, the dual track — are re-derived from it every run. If someone
 * loosens a threshold or a pattern, a real case changes verdict here.
 *
 * The live counterpart (`tests/Live/EngineFixtureLiveTest`) drives the same
 * fixtures through a real model and asserts the same expectations, which is
 * what catches the model itself moving underneath us.
 *
 * ## Adding a case
 *
 * Add a JSON file to `tests/Fixtures/Engine`. Every case must be one the
 * engine could get *wrong* — a case that could only ever pass teaches nothing
 * and costs a second on every run.
 */
class EngineFixtureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function fixtures(): array
    {
        $cases = [];

        // Data providers run before the application boots, so base_path()
        // is not available here yet.
        foreach (glob(dirname(__DIR__).'/Fixtures/Engine/*.json') as $path) {
            $fixture = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $cases[$fixture['name']] = [$fixture];
        }

        return $cases;
    }

    protected Assessment $hydrated;

    /**
     * @param  array<string, mixed>  $fixture
     * @return Collection<int, SignalExtraction>
     */
    protected function hydrate(array $fixture): Collection
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
        $answers = [];

        foreach ($fixture['answers'] as $index => $text) {
            $answers[$index] = Answer::create([
                'assessment_id' => $assessment->id,
                'question_id' => $questions[$index % $questions->count()]->id,
                'response_text' => $text,
            ]);
        }

        foreach ($fixture['signals'] as $position => $signal) {
            SignalExtraction::create([
                'assessment_id' => $assessment->id,
                'answer_id' => $answers[$signal['answer_index']]->id,
                'type' => SignalType::from($signal['type']),
                'track' => SignalTrack::from($signal['track']),
                'content' => $signal['content'],
                'verbatim' => $signal['verbatim'],
                'sort_order' => $position,
            ]);
        }

        $this->hydrated = $assessment;

        return $assessment->signalExtractions()->get();
    }

    /**
     * The harness is part of the test.
     *
     * Every fixture answer was being written to `answer_text` — not a column,
     * not fillable — so Eloquent discarded all of them without a word. The
     * deterministic layers here re-derive from recorded signals and never
     * noticed; the live harness handed a real model seven blank responses and
     * called the silence it got back a pass.
     *
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function what_the_student_wrote_survives_the_trip_into_the_database(array $fixture): void
    {
        $this->hydrate($fixture);

        $stored = $this->hydrated->answers()->pluck('response_text')->all();

        sort($stored);
        $expected = $fixture['answers'];
        sort($expected);

        $this->assertSame($expected, $stored);
    }

    /**
     * The claim the whole product rests on — that it surfaces what the student
     * said rather than writing what they would have said — checked against
     * whole real assessments rather than a constructed pair of strings.
     *
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function every_quoted_span_is_genuinely_theirs(array $fixture): void
    {
        $invented = [];

        foreach ($fixture['signals'] as $position => $signal) {
            if (! SignalExtractor::spanAppearsIn($signal['verbatim'], $fixture['answers'][$signal['answer_index']])) {
                $invented[] = 'S'.($position + 1).": \"{$signal['verbatim']}\"";
            }
        }

        // Asserted as a list rather than in the loop so that a case with no
        // signals still makes an assertion. A test that quietly performs none
        // is indistinguishable from one that passes, which is how a fixture
        // could rot without anyone noticing.
        $this->assertSame(
            [],
            $invented,
            "Signals in {$fixture['name']} quote things the student never said.",
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function the_red_team_verdict_has_not_moved(array $fixture): void
    {
        $findings = RedTeamLint::inspect($fixture['narrative']);
        $blocking = RedTeamLint::blocking($findings);

        if ($fixture['expect']['red_team_clean'] ?? true) {
            $this->assertSame(
                [],
                array_column($blocking, 'rule'),
                "The narrative in {$fixture['name']} used to ship and now would be refused.",
            );

            return;
        }

        $this->assertNotSame([], $blocking, "The narrative in {$fixture['name']} must be refused and no longer is.");

        foreach ($fixture['expect']['red_team_rules'] ?? [] as $rule) {
            $this->assertContains(
                $rule,
                array_column($blocking, 'rule'),
                "The lint stopped catching '{$rule}' in {$fixture['name']}.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function confidence_has_not_drifted(array $fixture): void
    {
        if (! isset($fixture['expect']['confidence'])) {
            $this->markTestSkipped('No confidence expectation recorded for this case.');
        }

        $this->hydrate($fixture);

        $this->assertSame(
            $fixture['expect']['confidence'],
            ConfidenceCalculator::overall($fixture['category_scores'], $fixture['answers'])->value,
            "Confidence moved on {$fixture['name']}.",
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function the_evidence_standing_has_not_drifted(array $fixture): void
    {
        if (! isset($fixture['expect']['evidence_standing'])) {
            $this->markTestSkipped('No standing expectation recorded for this case.');
        }

        $signals = $this->hydrate($fixture);
        $gap = DualTrack::gap(DualTrack::annotate($fixture['category_scores'], $signals));

        $this->assertSame($fixture['expect']['leading_category'], $gap['category']);
        $this->assertSame(
            $fixture['expect']['evidence_standing'],
            $gap['standing'],
            "The evidence standing moved on {$fixture['name']}.",
        );
    }

    /**
     * The case the dual track exists for: articulate and certain earns a high
     * confidence, and must still not be allowed to name a direction, because
     * confidence measures how clearly they answered and nothing else.
     *
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function fluency_never_substitutes_for_having_done_it(array $fixture): void
    {
        if (! array_key_exists('may_name_a_direction_on_evidence', $fixture['expect'])) {
            $this->markTestSkipped('This case does not test the confidence/evidence split.');
        }

        $signals = $this->hydrate($fixture);
        $gap = DualTrack::gap(DualTrack::annotate($fixture['category_scores'], $signals));

        $confidence = ConfidenceCalculator::overall($fixture['category_scores'], $fixture['answers']);

        $this->assertTrue(
            $confidence->permitsConclusion(),
            'This case is only meaningful while confidence alone would say yes.',
        );

        $this->assertSame(
            $fixture['expect']['may_name_a_direction_on_evidence'],
            $gap['supports_naming_a_direction'],
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function response_quality_has_not_drifted(array $fixture): void
    {
        $expected = $fixture['expect'];

        if (! isset($expected['response_quality_min']) && ! isset($expected['response_quality_max'])) {
            $this->markTestSkipped('No response-quality expectation recorded for this case.');
        }

        $signals = $this->hydrate($fixture);
        $scores = collect($fixture['answers'])->map(function (string $text) use ($signals) {
            $bands = ResponseQuality::bands($text, $signals);

            return (int) round(array_sum($bands));
        });

        $mean = (int) round($scores->avg());

        if (isset($expected['response_quality_min'])) {
            $this->assertGreaterThanOrEqual($expected['response_quality_min'], $mean, "Response quality fell on {$fixture['name']}.");
        }

        if (isset($expected['response_quality_max'])) {
            $this->assertLessThanOrEqual($expected['response_quality_max'], $mean, "Response quality rose on {$fixture['name']}.");
        }
    }

    /**
     * A corpus of cases the engine always passes is a corpus that has stopped
     * testing anything. This pins the shape of the library itself.
     */
    #[Test]
    public function the_library_still_contains_the_cases_that_can_fail(): void
    {
        $fixtures = array_map(fn (array $case) => $case[0], static::fixtures());

        $this->assertGreaterThanOrEqual(4, count($fixtures), 'The library shrank.');

        $standings = array_filter(array_column(array_column($fixtures, 'expect'), 'evidence_standing'));
        $confidences = array_filter(array_column(array_column($fixtures, 'expect'), 'confidence'));

        $this->assertContains(
            EvidenceStanding::AspirationOnly->value,
            $standings,
            'No case covers a student who only wants it. That is the failure 10.3 exists to prevent.',
        );

        $this->assertContains(
            ConfidenceLevel::InsufficientEvidence->value,
            $confidences,
            'No case covers an assessment too thin to interpret. That is the result the product is most tempted to fake.',
        );

        $this->assertContains(
            false,
            array_column(array_column($fixtures, 'expect'), 'red_team_clean'),
            'No case covers a narrative that must be refused.',
        );
    }

    /**
     * The lint reads three languages; for most of this product's life the
     * corpus only ever handed it one. A rule can be present in a phrase list
     * and still be unreachable in the language a student actually writes —
     * which is exactly what this assertion found on the day it was added.
     *
     * A refusal case alone would not be enough: deleting every Portuguese
     * rule and refusing all Portuguese would satisfy it. So each language
     * needs both a narrative that must be refused and one allowed to ship.
     */
    #[Test]
    public function the_corpus_speaks_every_language_the_product_does(): void
    {
        $fixtures = array_map(fn (array $case) => $case[0], static::fixtures());

        foreach (ConversationLocale::supported() as $locale) {
            $cases = array_filter(
                $fixtures,
                fn (array $fixture) => ($fixture['locale'] ?? 'en-US') === $locale,
            );

            $verdicts = array_column(array_column($cases, 'expect'), 'red_team_clean');

            $this->assertContains(true, $verdicts, "No {$locale} narrative in the corpus is allowed to ship.");
            $this->assertContains(false, $verdicts, "No {$locale} narrative in the corpus must be refused.");
        }
    }
}
