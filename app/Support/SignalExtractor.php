<?php

namespace App\Support;

use App\Ai\Agents\SignalDetection;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\SignalExtraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Blueprint Layer 4. Runs signal detection over an assessment's answers and
 * stores only the signals that can be traced back to words the person wrote.
 *
 * The brain's hard invariant is that it "surfaces what the student already
 * said; it does not write what they would have said." That is normally
 * unenforceable prose. Requiring every signal to carry the span it came from
 * turns it into a substring check: a quote the person never said is discarded
 * here, deterministically, with no model in the loop.
 */
class SignalExtractor
{
    /**
     * Characters that a model routinely "corrects" while copying, and that
     * carry no meaning for the purpose of proving a span came from the source.
     */
    protected const EQUIVALENCES = [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{2032}" => "'",
        "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-',
        "\u{2026}" => '...', "\u{00A0}" => ' ',
    ];

    /**
     * Extract and persist the signals for an assessment, replacing any from a
     * previous run so re-analysis is idempotent.
     *
     * @return Collection<int, SignalExtraction>
     */
    public function extract(Assessment $assessment, string $locale = ConversationLocale::DEFAULT): Collection
    {
        $answers = $this->answers($assessment);

        if ($answers->isEmpty()) {
            return collect();
        }

        $payload = $answers->map(fn (Answer $answer) => [
            'id' => (string) $answer->id,
            'question' => $answer->question?->localizedQuestionText($locale) ?? '',
            'response' => $this->responseText($answer),
        ])->values()->all();

        $agent = new SignalDetection($payload, $locale);
        $response = $agent->prompt($agent->buildPrompt());

        return $this->persist($assessment, $answers, $response->structured['signals'] ?? []);
    }

    /**
     * @param  Collection<string, Answer>  $answers  keyed by answer id
     * @param  array<int, array<string, mixed>>  $signals
     * @return Collection<int, SignalExtraction>
     */
    protected function persist(Assessment $assessment, Collection $answers, array $signals): Collection
    {
        $assessment->signalExtractions()->delete();

        $rows = [];
        $order = 0;

        foreach ($signals as $signal) {
            $accepted = $this->accept($assessment, $answers, $signal);

            if ($accepted === null) {
                continue;
            }

            $accepted['sort_order'] = $order++;
            $rows[] = $accepted;
        }

        if ($rows === []) {
            return collect();
        }

        return collect(array_map(
            fn (array $row) => $assessment->signalExtractions()->create($row),
            $rows,
        ));
    }

    /**
     * Validate a single emitted signal, returning the attributes to store or
     * null if it must be discarded.
     *
     * Four ways a signal fails, all of them silent corruption if stored:
     * an unknown type or track, an answer id that was never sent, and — the
     * one this whole layer exists for — a verbatim span the person never wrote.
     *
     * @param  Collection<string, Answer>  $answers
     * @param  array<string, mixed>  $signal
     * @return array<string, mixed>|null
     */
    protected function accept(Assessment $assessment, Collection $answers, array $signal): ?array
    {
        $type = SignalType::tryFrom((string) ($signal['type'] ?? ''));
        $track = SignalTrack::tryFrom((string) ($signal['track'] ?? ''));
        $answer = $answers->get((string) ($signal['answer_id'] ?? ''));
        $verbatim = trim((string) ($signal['verbatim'] ?? ''));
        $content = trim((string) ($signal['content'] ?? ''));

        if (! $type || ! $track || ! $answer || $verbatim === '' || $content === '') {
            return $this->discard($assessment, $signal, 'malformed');
        }

        if (! static::spanAppearsIn($verbatim, $this->responseText($answer))) {
            return $this->discard($assessment, $signal, 'verbatim_not_found');
        }

        return [
            'answer_id' => $answer->id,
            'type' => $type,
            'track' => $track,
            'content' => $content,
            'verbatim' => $verbatim,
            'constraint_nature' => $this->constraintNature($type, $signal),
        ];
    }

    /**
     * Whether a span really occurs in the source text.
     *
     * The comparison normalises whitespace runs and the punctuation a model
     * reflexively prettifies (curly quotes, em dashes) but nothing else. It
     * deliberately does not fold case, stem, or fuzzy-match: the point is to
     * prove these are the person's words, and a looser test would start
     * accepting words they never used.
     */
    public static function spanAppearsIn(string $span, string $source): bool
    {
        $normalize = static function (string $value): string {
            $value = strtr($value, static::EQUIVALENCES);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return trim($value);
        };

        $span = $normalize($span);

        return $span !== '' && str_contains($normalize($source), $span);
    }

    /**
     * Constraints must declare their nature; nothing else may claim one.
     *
     * The blueprint treats a constraint's nature as load-bearing — it decides
     * whether a pathway is closed, delayed, or merely inconvenient — so an
     * unusable value on a real constraint is recorded as null rather than
     * guessed at.
     *
     * @param  array<string, mixed>  $signal
     */
    protected function constraintNature(SignalType $type, array $signal): ?string
    {
        if ($type !== SignalType::Constraint) {
            return null;
        }

        $nature = (string) ($signal['constraint_nature'] ?? '');

        return in_array($nature, ['fixed', 'temporary', 'negotiable'], true) ? $nature : null;
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    protected function discard(Assessment $assessment, array $signal, string $reason): null
    {
        Log::warning('signal_discarded', [
            'assessment_id' => $assessment->id,
            'reason' => $reason,
            'type' => $signal['type'] ?? null,
            'answer_id' => $signal['answer_id'] ?? null,
            'verbatim' => mb_substr((string) ($signal['verbatim'] ?? ''), 0, 120),
        ]);

        return null;
    }

    /**
     * @return Collection<string, Answer>
     */
    protected function answers(Assessment $assessment): Collection
    {
        return $assessment->answers()
            ->with('question.translations')
            ->orderBy('id')
            ->get()
            ->filter(fn (Answer $answer) => filled($this->responseText($answer)))
            ->keyBy(fn (Answer $answer) => (string) $answer->id);
    }

    protected function responseText(Answer $answer): string
    {
        return trim((string) ($answer->response_text ?: $answer->audio_transcript));
    }
}
