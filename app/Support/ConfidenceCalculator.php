<?php

namespace App\Support;

use App\Enums\ConfidenceLevel;
use Illuminate\Support\Collection;

/**
 * Derives blueprint 8.6 confidence levels from the analysis and its evidence.
 *
 * Confidence is computed here rather than asked of the model. A model asked
 * how confident it is reports how fluent its own prose felt, which is exactly
 * uncorrelated with how much the student actually said. The blueprint requires
 * the opposite: "A wise system must know when not to over-speak."
 *
 * Three independent things can lower a category's confidence:
 *
 * 1. **Its score.** The raw interpretive strength of the match.
 * 2. **The evidence ceiling.** Thin or vague answers cap every category, no
 *    matter how high it scored. Blueprint 10.5: "Your answers give me a
 *    starting point, but they are too brief for a high-confidence result."
 * 3. **Separation.** A leader inside the clustering threshold of its rival is
 *    not a strong signal, it is an unresolved one.
 *
 * The lowest of the three wins.
 */
class ConfidenceCalculator
{
    /**
     * Score at or above which a category reads as each level, strongest first.
     */
    public const THRESHOLDS = [
        80 => ConfidenceLevel::Strong,
        65 => ConfidenceLevel::Moderate,
        50 => ConfidenceLevel::Emerging,
        30 => ConfidenceLevel::Weak,
    ];

    /**
     * An answer shorter than this many words is not substantive evidence.
     */
    public const SUBSTANTIVE_WORDS = 8;

    /**
     * Fewer substantive answers than this and nothing can be concluded.
     */
    public const MINIMUM_SUBSTANTIVE_ANSWERS = 3;

    /**
     * A response_quality_score below this carries little evidence, and is the
     * quality-based analogue of SUBSTANTIVE_WORDS.
     */
    public const SUBSTANTIVE_QUALITY = 35;

    /**
     * The level a score alone would imply, before any capping.
     */
    public static function fromScore(int|float $score): ConfidenceLevel
    {
        foreach (self::THRESHOLDS as $threshold => $level) {
            if ($score >= $threshold) {
                return $level;
            }
        }

        return ConfidenceLevel::InsufficientEvidence;
    }

    /**
     * The strongest level the body of evidence can support.
     *
     * Word counts are the fallback, not the intent. When Layer 4 has run,
     * per-answer quality scores are available and are a far better measure:
     * they count verified signals rather than words, so they do not reward a
     * fluent student for saying nothing, and they work in any language. See
     * {@see ResponseQuality}.
     *
     * @param  list<string>  $answers  the respondent's own words, one per answer
     * @param  list<int>|null  $qualityScores  response_quality_score per answer, when scored
     */
    public static function evidenceCeiling(array $answers, ?array $qualityScores = null): ConfidenceLevel
    {
        if ($qualityScores !== null) {
            return self::ceilingFromQuality($qualityScores);
        }

        $words = collect($answers)
            ->map(fn (string $answer) => WordCount::of($answer))
            ->filter(fn (int $count) => $count > 0)
            ->values();

        if ($words->isEmpty()) {
            return ConfidenceLevel::InsufficientEvidence;
        }

        $substantive = $words->filter(fn (int $count) => $count >= self::SUBSTANTIVE_WORDS)->count();

        if ($substantive < self::MINIMUM_SUBSTANTIVE_ANSWERS) {
            return ConfidenceLevel::InsufficientEvidence;
        }

        $median = self::median($words);

        return match (true) {
            $median >= 40 && $substantive >= 8 => ConfidenceLevel::Strong,
            $median >= 20 && $substantive >= 5 => ConfidenceLevel::Moderate,
            $median >= self::SUBSTANTIVE_WORDS => ConfidenceLevel::Emerging,
            default => ConfidenceLevel::Weak,
        };
    }

    /**
     * The ceiling implied by scored answers.
     *
     * Deliberately parallel in shape to the word-count path so the two cannot
     * drift into disagreeing about what "thin" means.
     *
     * @param  list<int>  $qualityScores
     */
    protected static function ceilingFromQuality(array $qualityScores): ConfidenceLevel
    {
        $scores = collect($qualityScores)
            ->map(fn ($score) => (int) $score)
            ->filter(fn (int $score) => $score > 0)
            ->values();

        if ($scores->isEmpty()) {
            return ConfidenceLevel::InsufficientEvidence;
        }

        $substantive = $scores->filter(fn (int $score) => $score >= self::SUBSTANTIVE_QUALITY)->count();

        if ($substantive < self::MINIMUM_SUBSTANTIVE_ANSWERS) {
            return ConfidenceLevel::InsufficientEvidence;
        }

        $median = self::median($scores);

        return match (true) {
            $median >= 70 && $substantive >= 8 => ConfidenceLevel::Strong,
            $median >= 50 && $substantive >= 5 => ConfidenceLevel::Moderate,
            $median >= self::SUBSTANTIVE_QUALITY => ConfidenceLevel::Emerging,
            default => ConfidenceLevel::Weak,
        };
    }

    /**
     * Annotate each category score with its derived confidence.
     *
     * @param  list<array{category?: string, score?: int|float, rationale?: string}>  $categoryScores
     * @param  list<string>  $answers
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $categoryScores, array $answers, ?array $qualityScores = null): array
    {
        $ceiling = self::evidenceCeiling($answers, $qualityScores);
        $ranked = collect($categoryScores)
            ->sortByDesc(fn (array $row) => $row['score'] ?? 0)
            ->values();

        $runnerUp = (float) ($ranked->get(1)['score'] ?? 0);

        return $ranked->map(function (array $row, int $index) use ($ceiling, $runnerUp) {
            $score = (float) ($row['score'] ?? 0);
            $level = self::fromScore($score)->cappedAt($ceiling);

            // The leader is only as confident as its separation from the next
            // pathway. Inside the clustering threshold they are rivals, not a
            // winner and a runner-up.
            if ($index === 0 && $score - $runnerUp < CompetingPathways::CLUSTER_THRESHOLD) {
                $level = $level->cappedAt(ConfidenceLevel::Moderate);
            }

            $row['confidence'] = $level->value;

            return $row;
        })->all();
    }

    /**
     * The confidence of the result as a whole: that of its leading category.
     *
     * @param  list<array{category?: string, score?: int|float}>  $categoryScores
     * @param  list<string>  $answers
     */
    public static function overall(array $categoryScores, array $answers, ?array $qualityScores = null): ConfidenceLevel
    {
        $annotated = self::annotate($categoryScores, $answers, $qualityScores);

        if ($annotated === []) {
            return ConfidenceLevel::InsufficientEvidence;
        }

        return ConfidenceLevel::from($annotated[0]['confidence']);
    }

    /**
     * Why confidence landed where it did, in plain language.
     *
     * The blueprint requires the system to be able to explain a level, and
     * forbids "There is not enough information to help you." Every branch
     * here names what is missing rather than what is absent.
     *
     * @param  list<array{category?: string, score?: int|float}>  $categoryScores
     * @param  list<string>  $answers
     * @return array{level: ConfidenceLevel, rationale: string, missing_evidence: list<string>}
     */
    public static function explain(array $categoryScores, array $answers, ?array $qualityScores = null): array
    {
        $level = self::overall($categoryScores, $answers, $qualityScores);
        $ceiling = self::evidenceCeiling($answers, $qualityScores);
        $missing = [];

        // Measure "thin" against whichever evidence source set the ceiling, so
        // the explanation cannot contradict the level it is explaining.
        [$measures, $bar] = $qualityScores !== null
            ? [collect($qualityScores)->map(fn ($score) => (int) $score), self::SUBSTANTIVE_QUALITY]
            : [collect($answers)->map(fn (string $answer) => WordCount::of($answer)), self::SUBSTANTIVE_WORDS];

        $measures = $measures->filter(fn (int $value) => $value > 0)->values();
        $substantive = $measures->filter(fn (int $value) => $value >= $bar)->count();

        if ($measures->isEmpty()) {
            $missing[] = 'Answers in your own words — there is nothing yet to interpret.';
        } elseif ($substantive < self::MINIMUM_SUBSTANTIVE_ANSWERS) {
            $missing[] = 'Longer answers on at least a few questions. Short answers give a starting point but not a direction.';
        } elseif ($ceiling->rank() > ConfidenceLevel::Moderate->rank()) {
            $missing[] = 'More detail in your answers — specific situations, not summaries.';
        }

        $ranked = collect($categoryScores)->sortByDesc(fn (array $row) => $row['score'] ?? 0)->values();
        $leader = (float) ($ranked->first()['score'] ?? 0);
        $runnerUp = (float) ($ranked->get(1)['score'] ?? 0);

        if ($ranked->isNotEmpty() && $leader - $runnerUp < CompetingPathways::CLUSTER_THRESHOLD) {
            $missing[] = 'Something that separates your top pathways — they are currently supported by the same evidence.';
        }

        if ($ranked->isNotEmpty() && $leader < 50) {
            $missing[] = 'Evidence of what you have actually done, not only what interests you.';
        }

        $rationale = match (true) {
            $level === ConfidenceLevel::Strong => 'Your answers were specific and consistent enough to support a clear reading.',
            $level === ConfidenceLevel::Moderate => 'There is a real pattern here, and it is worth testing rather than assuming.',
            $level === ConfidenceLevel::Emerging => 'Something is taking shape, but it is early. This is a hypothesis, not a result.',
            $level === ConfidenceLevel::Weak => 'There is not enough evidence yet to make a strong interpretation, but there is enough to know what we need to test next.',
            default => 'There is not enough evidence yet to make an interpretation — but there is enough to know what to look for next.',
        };

        return [
            'level' => $level,
            'rationale' => $rationale,
            'missing_evidence' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @param  Collection<int, int>  $values
     */
    protected static function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($sorted[$middle - 1] + $sorted[$middle]) / 2
            : (float) $sorted[$middle];
    }
}
