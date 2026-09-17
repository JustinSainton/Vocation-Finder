<?php

namespace App\Support;

use App\Enums\SignalTrack;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\SignalExtraction;
use Illuminate\Support\Collection;

/**
 * Scores how much evidence an answer actually carries, 0-100.
 *
 * Blueprint 11 is explicit that answer quality is "specificity, reflection,
 * coherence, behavioral grounding — NOT writing or speaking skill." That rules
 * out every convenient proxy. Vocabulary, spelling, punctuation, sentence
 * construction and proper-noun density all track literacy and schooling, and
 * scoring on them would quietly penalise exactly the students this product
 * exists to take seriously.
 *
 * So the score is built from three things that survive that constraint:
 *
 * 1. **Substance** — how much they said, saturating quickly. Length is weak
 *    evidence but it is not zero evidence, and it is polish-blind.
 * 2. **Evidence density** — how many Layer 4 signals the answer yielded. Every
 *    one has already been verified as a span the person actually wrote, so
 *    this measures how much interpretable material is present, in any
 *    language, at any level of polish.
 * 3. **Behavioral grounding** — whether any of those signals are on the
 *    demonstrated track. "I want to help people" and "I sat with her until she
 *    stopped crying" are not the same evidence, and 10.3 turns on the
 *    difference.
 *
 * Nothing here asks a model for a number it could compute instead.
 */
class ResponseQuality
{
    /**
     * Band ceilings. Substance is deliberately the smallest of the three: it
     * is the weakest evidence and the easiest to game by rambling.
     */
    public const SUBSTANCE_MAX = 30;

    public const DENSITY_MAX = 40;

    public const GROUNDING_MAX = 30;

    /**
     * Words at which the substance band saturates. Past this, saying more is
     * not saying more that matters.
     */
    public const SUBSTANCE_SATURATION = 60;

    /**
     * Signals at which the density band saturates.
     */
    public const DENSITY_SATURATION = 4;

    /**
     * Score every answer on an assessment and persist the results.
     *
     * @return Collection<string, int> keyed by answer id
     */
    public function scoreAssessment(Assessment $assessment): Collection
    {
        $signals = $assessment->signalExtractions()->get()->groupBy('answer_id');
        $extractionRan = $assessment->signalExtractions()->exists();

        return $assessment->answers()->get()->mapWithKeys(function (Answer $answer) use ($signals, $extractionRan) {
            $bands = static::bands(
                static::responseText($answer),
                $signals->get($answer->id) ?? collect(),
                $extractionRan,
            );

            $answer->forceFill([
                'response_quality_score' => $bands['total'],
                'response_quality_bands' => $bands,
            ])->save();

            return [(string) $answer->id => $bands['total']];
        });
    }

    /**
     * The band breakdown for one answer.
     *
     * @param  Collection<int, SignalExtraction>  $signals
     * @return array{substance: int, density: int, grounding: int, total: int}
     */
    public static function bands(string $text, Collection $signals, bool $extractionRan = true): array
    {
        $words = WordCount::of($text);

        if ($words === 0) {
            return ['substance' => 0, 'density' => 0, 'grounding' => 0, 'total' => 0];
        }

        $substance = (int) round(static::SUBSTANCE_MAX * min(1.0, $words / static::SUBSTANCE_SATURATION));

        // Layer 4 is allowed to fail without taking the analysis with it. When
        // it did not run at all, the two signal-derived bands carry no
        // information, and scoring them zero would misreport a rich answer as
        // empty. Substance is scaled up to stand alone instead.
        if (! $extractionRan) {
            $total = (int) round(100 * min(1.0, $words / static::SUBSTANCE_SATURATION));

            return ['substance' => $substance, 'density' => 0, 'grounding' => 0, 'total' => $total];
        }

        $density = (int) round(static::DENSITY_MAX * min(1.0, $signals->count() / static::DENSITY_SATURATION));

        $demonstrated = $signals->filter(
            fn (SignalExtraction $signal) => $signal->track === SignalTrack::Demonstrated
        )->count();

        $grounding = (int) round(static::GROUNDING_MAX * min(1.0, $demonstrated / 2));

        return [
            'substance' => $substance,
            'density' => $density,
            'grounding' => $grounding,
            'total' => $substance + $density + $grounding,
        ];
    }

    protected static function responseText(Answer $answer): string
    {
        return trim((string) ($answer->response_text ?: $answer->audio_transcript));
    }
}
