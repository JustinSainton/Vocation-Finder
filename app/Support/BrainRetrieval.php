<?php

namespace App\Support;

use App\Models\BrainEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads the vocational brain back out.
 *
 * Note what this class does not have: an entitlement check, a subscription
 * check, and a model. All three are absent on purpose.
 *
 * - No entitlement check, because a lapse freezes the brain rather than
 *   destroying it, and export must always work. A student who stops paying
 *   still gets their own words.
 * - No model, because the moment retrieval summarises, the thing being handed
 *   back is the system's sentence rather than the student's. Search here is
 *   keyword matching over stored text and returns rows unaltered.
 */
class BrainRetrieval
{
    /**
     * Words too common to narrow anything, stripped so that searching for
     * "what I said about people" does not match every entry.
     */
    protected const STOP_WORDS = [
        'the', 'and', 'for', 'that', 'this', 'with', 'what', 'about', 'when',
        'have', 'has', 'was', 'were', 'you', 'your', 'they', 'them', 'their',
        'from', 'said', 'say', 'like', 'just', 'because', 'been', 'are', 'did',
    ];

    /**
     * @return Collection<int, BrainEntry>
     */
    public function search(User $user, string $topic, int $limit = 10): Collection
    {
        $terms = static::terms($topic);

        if ($terms === []) {
            return $this->recent($user, $limit);
        }

        return $user->brainEntries()
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('content', 'like', '%'.$term.'%');
                }
            })
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, BrainEntry>
     */
    public function recent(User $user, int $limit = 10): Collection
    {
        return $user->brainEntries()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Everything, oldest first, for the student to take with them.
     *
     * Chronological because the export is a record of someone becoming
     * something over time, and that is only legible in order.
     *
     * @return list<array<string, mixed>>
     */
    public function export(User $user): array
    {
        return $user->brainEntries()
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (BrainEntry $entry) => [
                'said_on' => $entry->occurred_at->toDateString(),
                'context' => $entry->context,
                'in_their_words' => $entry->content,
                'source' => $entry->source->label(),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    protected static function terms(string $topic): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($topic)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) > 2 && ! in_array($word, self::STOP_WORDS, true),
        ));
    }
}
