<?php

namespace App\Support;

use App\Models\BrainEntry;
use App\Models\SurfacedPattern;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Roadmap 2.5 — interrupting when a pattern repeats enough to name.
 *
 * ## It never names the pattern
 *
 * The "name" returned here is the student's own repeated word, lifted
 * verbatim, together with the sentences they said it in. There is no model
 * call and no generated label, because the moment the system supplies the
 * word it has written what the student would have said — which is the one
 * thing the brain must never do. The interruption is "you have said this four
 * times since March, here are the four times", and the student does the
 * naming.
 *
 * ## Repetition is not the same as emphasis
 *
 * A term must appear in several *distinct* entries spread over several weeks.
 * Saying "engineering" four times inside one conversation is one thought
 * arriving with enthusiasm, not a pattern, and treating it as one would teach
 * a student that the system mistakes volume for meaning.
 *
 * ## One pattern, once
 *
 * The strongest unsurfaced pattern only, and never the same one twice inside
 * the cooldown. Said once, "you keep coming back to this" is the product
 * working. Said on every visit it is nagging, and a tool that nags a teenager
 * about their own words gets closed.
 */
class ThresholdSurfacing
{
    /**
     * Distinct entries a term must appear in before it is a pattern.
     */
    public const MIN_ENTRIES = 4;

    /**
     * Days the first and last mention must be apart. This is the rule that
     * separates a pattern from an enthusiasm.
     */
    public const MIN_SPAN_DAYS = 21;

    /**
     * How long before the same term may be raised again.
     */
    public const COOLDOWN_DAYS = 90;

    protected const MIN_TERM_LENGTH = 4;

    /**
     * Words that, repeated, are not a vocational pattern.
     *
     * ⚠️ A student who has said "hopeless" in six entries across two months
     * has told us something, and it is not that they are drawn to a pathway.
     * Surfacing it as a vocational pattern would be the product performing
     * interpretation on distress, which the safety invariant forbids — crisis
     * reaches a person before it reaches vocational meaning. These are
     * excluded from surfacing and reported separately so the caller escalates
     * rather than interprets.
     *
     * @var list<string>
     */
    protected const DISTRESS = [
        'hopeless', 'worthless', 'pointless', 'suicidal', 'kill', 'die', 'dying',
        'hurt', 'hurting', 'hate', 'hates', 'hating', 'scared', 'terrified',
        'panic', 'panicking', 'alone', 'lonely', 'depressed', 'depression',
        'anxious', 'anxiety', 'abuse', 'abused', 'starving', 'homeless',
    ];

    /**
     * Words too ordinary to be a pattern. Broader than the retrieval stop
     * list, because a search term is supplied deliberately by someone who
     * means it while this reads whatever a teenager happened to type.
     *
     * @var list<string>
     */
    protected const TOO_ORDINARY = [
        'about', 'after', 'again', 'also', 'always', 'another', 'anything',
        'around', 'because', 'been', 'being', 'better', 'could', 'didn',
        'different', 'does', 'doing', 'done', 'down', 'even', 'ever', 'every',
        'feel', 'feels', 'felt', 'from', 'getting', 'going', 'good', 'guess',
        'have', 'having', 'here', 'into', 'just', 'kind', 'know', 'like',
        'little', 'long', 'looking', 'lots', 'made', 'make', 'makes', 'many',
        'maybe', 'mean', 'more', 'most', 'much', 'need', 'never', 'nice',
        'only', 'other', 'over', 'people', 'pretty', 'probably', 'really',
        'right', 'same', 'seems', 'since', 'some', 'something', 'sometimes',
        'still', 'stuff', 'such', 'sure', 'take', 'than', 'that', 'their',
        'them', 'then', 'there', 'these', 'they', 'thing', 'things', 'think',
        'this', 'those', 'though', 'through', 'time', 'times', 'told', 'took',
        'trying', 'very', 'want', 'wanted', 'well', 'went', 'were', 'what',
        'when', 'where', 'which', 'while', 'will', 'with', 'work', 'would',
        'your', 'yourself',
    ];

    /**
     * The one pattern worth interrupting for, or null.
     *
     * @return array{term: string, entry_count: int, first_said_on: string, last_said_on: string, in_their_words: list<array{said_on: string, content: string}>, needs_human: bool}|null
     */
    public function detect(User $user, ?CarbonImmutable $asOf = null): ?array
    {
        $now = $asOf ?? CarbonImmutable::now();
        $entries = $user->brainEntries()->orderBy('occurred_at')->get();

        if ($entries->count() < self::MIN_ENTRIES) {
            return null;
        }

        $cooling = SurfacedPattern::query()
            ->where('user_id', $user->id)
            ->where('surfaced_at', '>=', $now->subDays(self::COOLDOWN_DAYS))
            ->pluck('term')
            ->all();

        $candidates = $this->candidates($entries, $cooling);

        if ($candidates->isEmpty()) {
            return null;
        }

        /**
         * Strongest first: more entries beats fewer, and a longer span breaks
         * the tie. A word said across four months is a better thing to raise
         * than one said across four weeks, even at the same count.
         */
        $winner = $candidates
            ->sortByDesc(fn (array $candidate) => [$candidate['entry_count'], $candidate['span']])
            ->first();

        return [
            'term' => $winner['term'],
            'entry_count' => $winner['entry_count'],
            'first_said_on' => $winner['first']->toDateString(),
            'last_said_on' => $winner['last']->toDateString(),
            // Their sentences, unaltered. This is the whole payload — there
            // is deliberately nothing here that the system wrote.
            'in_their_words' => $winner['entries'],
            'needs_human' => $winner['distress'],
        ];
    }

    /**
     * Write down that this was raised, so it is not raised again.
     *
     * @param  array<string, mixed>  $pattern
     */
    public function record(User $user, array $pattern, ?CarbonImmutable $asOf = null): SurfacedPattern
    {
        return SurfacedPattern::create([
            'user_id' => $user->id,
            'term' => $pattern['term'],
            'entry_count' => $pattern['entry_count'],
            'first_said_on' => $pattern['first_said_on'],
            'last_said_on' => $pattern['last_said_on'],
            'surfaced_at' => $asOf ?? CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  Collection<int, BrainEntry>  $entries
     * @param  list<string>  $cooling
     * @return Collection<int, array<string, mixed>>
     */
    protected function candidates(Collection $entries, array $cooling): Collection
    {
        $byTerm = [];

        foreach ($entries as $entry) {
            // Counted once per entry, however many times it occurs inside
            // one. Repeating a word in a single paragraph is emphasis.
            foreach (array_unique($this->terms($entry->content)) as $term) {
                $byTerm[$term][] = $entry;
            }
        }

        return collect($byTerm)
            ->reject(fn (array $matches, string $term) => in_array($term, $cooling, true))
            ->filter(fn (array $matches) => count($matches) >= self::MIN_ENTRIES)
            ->map(function (array $matches, string $term) {
                $first = CarbonImmutable::parse($matches[0]->occurred_at)->startOfDay();
                $last = CarbonImmutable::parse(end($matches)->occurred_at)->startOfDay();

                return [
                    'term' => $term,
                    'entry_count' => count($matches),
                    'first' => $first,
                    'last' => $last,
                    'span' => $first->diffInDays($last),
                    'distress' => in_array($term, self::DISTRESS, true),
                    'entries' => array_map(fn (BrainEntry $entry) => [
                        'said_on' => $entry->occurred_at->toDateString(),
                        'content' => $entry->content,
                    ], $matches),
                ];
            })
            ->filter(fn (array $candidate) => $candidate['span'] >= self::MIN_SPAN_DAYS)
            ->values();
    }

    /**
     * @return list<string>
     */
    protected function terms(string $content): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= self::MIN_TERM_LENGTH
                && ! in_array($word, self::TOO_ORDINARY, true),
        ));
    }
}
