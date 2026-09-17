<?php

namespace App\Support;

use App\Enums\EvidenceStanding;
use App\Enums\SignalTrack;
use App\Models\SignalExtraction;
use Illuminate\Support\Collection;

/**
 * Blueprint 10.3 — aspirational and demonstrated fit, scored separately.
 *
 * The blueprint's reason for the split is that "the distance between the two
 * generates the development plan and real-world experiments." A student who
 * wants to be a paramedic and a student who has run the first-aid tent at
 * camp for two summers must not produce the same result, and the second is
 * not *better* — they simply need different next moves.
 *
 * ## Why the model cites and this class counts
 *
 * Layer 4 runs before taxonomy mapping, so a signal cannot know which
 * category it supports. The only place that knowledge exists is the analysis
 * pass, which sees the signals and the taxonomy together. So the model is
 * asked for the one thing it is actually qualified to supply — *which signals
 * bear on which pathway* — and nothing else. It never supplies the weights,
 * the tracks or the standing: those are computed here from `SignalType` and
 * `SignalTrack`, which the model cannot influence.
 *
 * A citation to a signal that does not exist is dropped, exactly as an
 * invented verbatim span is dropped in {@see SignalExtractor}. The claim that
 * every track weight traces to a real, verified quote is therefore a
 * substring-and-lookup check rather than a hope.
 */
class DualTrack
{
    /**
     * Enough demonstrated weight to call a pathway demonstrated.
     *
     * 1.5 is two full-weight signals (burden, skill or strength at 1.0) minus
     * a little, or one full-weight signal plus a development need. One
     * instance of anything is an anecdote; the threshold exists so that a
     * single summer job cannot, by itself, tell a sixteen-year-old who they
     * are.
     */
    protected const DEMONSTRATED_THRESHOLD = 1.5;

    /**
     * The signals of an assessment, keyed by the reference the prompt shows.
     *
     * Ordering is fixed here rather than at the call sites so the prompt and
     * the resolver cannot drift apart — a reference that means one signal
     * when rendered and another when resolved would silently reattribute
     * evidence, and nothing downstream would look wrong.
     *
     * @param  Collection<int, SignalExtraction>  $signals
     * @return array<string, SignalExtraction>
     */
    public static function index(Collection $signals): array
    {
        return $signals
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->mapWithKeys(fn (SignalExtraction $signal, int $position) => ['S'.($position + 1) => $signal])
            ->all();
    }

    /**
     * Annotate each category score with where its case actually rests.
     *
     * Additive by design: a row whose `evidence` the model omitted, or whose
     * citations all turn out to be invented, comes back `unevidenced` rather
     * than breaking. An assessment that predates Layer 4 keeps working and
     * every pathway is simply not yet evidenced, which is true.
     *
     * @param  array<int, array<string, mixed>>  $categoryScores
     * @param  Collection<int, SignalExtraction>  $signals
     * @return array<int, array<string, mixed>>
     */
    public static function annotate(array $categoryScores, Collection $signals): array
    {
        $index = static::index($signals);

        return collect($categoryScores)
            ->map(function (array $row) use ($index) {
                $cited = collect($row['evidence'] ?? [])
                    ->filter(fn ($ref) => is_string($ref) && isset($index[$ref]))
                    ->unique()
                    ->values();

                $demonstrated = 0.0;
                $aspiration = 0.0;

                foreach ($cited as $ref) {
                    $signal = $index[$ref];

                    if ($signal->track === SignalTrack::Demonstrated
                        && $signal->type->canEvidenceDemonstratedFit()) {
                        $demonstrated += $signal->type->weight();

                        continue;
                    }

                    $aspiration += $signal->type->weight();
                }

                $row['evidence'] = $cited->all();
                $row['demonstrated_weight'] = round($demonstrated, 2);
                $row['aspiration_weight'] = round($aspiration, 2);
                $row['evidence_standing'] = static::standing($demonstrated, $aspiration)->value;

                return $row;
            })
            ->all();
    }

    /**
     * The citations the model offered that name nothing we extracted.
     *
     * {@see annotate()} drops them silently, which is correct — a reference to
     * something that is not a signal cannot earn evidentiary weight. But the
     * drop is also the single most informative thing about a weak model: it
     * cites the question text, or the answer text, or an invented `S9`, and
     * every layer keeps working while the standing collapses to
     * `unevidenced`. Reported here so the caller can say so out loud.
     *
     * @param  array<int, array<string, mixed>>  $categoryScores
     * @param  Collection<int, SignalExtraction>  $signals
     * @return list<string>
     */
    public static function uncitedReferences(array $categoryScores, Collection $signals): array
    {
        $index = static::index($signals);

        return collect($categoryScores)
            ->flatMap(fn (array $row) => $row['evidence'] ?? [])
            ->reject(fn ($ref) => is_string($ref) && isset($index[$ref]))
            ->map(fn ($ref) => is_string($ref) ? $ref : json_encode($ref))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Which of the four standings a pair of weights lands on.
     */
    public static function standing(float $demonstrated, float $aspiration): EvidenceStanding
    {
        if ($demonstrated >= static::DEMONSTRATED_THRESHOLD) {
            return EvidenceStanding::Demonstrated;
        }

        if ($demonstrated > 0.0) {
            return EvidenceStanding::Emerging;
        }

        if ($aspiration > 0.0) {
            return EvidenceStanding::AspirationOnly;
        }

        return EvidenceStanding::Unevidenced;
    }

    /**
     * The distance, for the leading pathway, stated as a next move.
     *
     * Only the leader gets one. A student handed four distances has been
     * handed a report; a student handed one has been handed something to do,
     * which is the whole thesis of the product.
     *
     * @param  array<int, array<string, mixed>>  $annotatedScores
     * @return array{category: string, standing: string, meaning: string, next_move: string, supports_naming_a_direction: bool}|null
     */
    public static function gap(array $annotatedScores): ?array
    {
        $leader = collect($annotatedScores)
            ->filter(fn (array $row) => filled($row['category'] ?? null))
            ->sortByDesc(fn (array $row) => $row['score'] ?? 0)
            ->first();

        if ($leader === null) {
            return null;
        }

        $standing = EvidenceStanding::tryFrom($leader['evidence_standing'] ?? '')
            ?? EvidenceStanding::Unevidenced;

        return [
            'category' => (string) $leader['category'],
            'standing' => $standing->value,
            'meaning' => $standing->meaning(),
            'next_move' => $standing->nextMove(),
            'supports_naming_a_direction' => $standing->supportsNamingADirection(),
        ];
    }
}
