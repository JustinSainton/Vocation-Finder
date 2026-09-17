<?php

namespace App\Support;

use App\Models\VocationalCategory;
use Illuminate\Support\Collection;

/**
 * Decides whether a first-pass result is ambiguous enough to justify a second,
 * more expensive interpretive pass.
 *
 * Blueprint layers 5 and 6 are distinct for a reason: mapping to the taxonomy
 * and resolving between competing mappings are different operations. Most
 * assessments produce a clear leader and need only the first. When the top
 * scores cluster, the engine is effectively guessing, and guessing is the
 * failure mode the blueprint exists to prevent.
 */
class CompetingPathways
{
    /**
     * Scores closer to the leader than this are treated as genuinely competing.
     */
    public const CLUSTER_THRESHOLD = 8;

    /**
     * Below this, a category is not a serious candidate regardless of how close
     * it sits to the leader. Two weak scores tying is not a real competition.
     */
    public const CANDIDATE_FLOOR = 55;

    /**
     * More candidates than this and the problem is not disambiguation — it is
     * a thin assessment, which low-confidence mode handles instead.
     */
    public const MAX_CANDIDATES = 4;

    /**
     * Category slugs that genuinely compete for the top of this result, or an
     * empty array when the leader is clear.
     *
     * @param  array<int, array{category?: string, score?: int|float}>  $categoryScores
     * @return array<int, string>
     */
    public static function detect(array $categoryScores): array
    {
        $ranked = static::rank($categoryScores);

        if ($ranked->count() < 2) {
            return [];
        }

        $leader = (float) $ranked->first()['score'];

        if ($leader < static::CANDIDATE_FLOOR) {
            return [];
        }

        $candidates = $ranked
            ->filter(fn (array $row) => (float) $row['score'] >= static::CANDIDATE_FLOOR)
            ->filter(fn (array $row) => $leader - (float) $row['score'] <= static::CLUSTER_THRESHOLD)
            ->take(static::MAX_CANDIDATES);

        if ($candidates->count() < 2) {
            return [];
        }

        return $candidates->pluck('slug')->values()->all();
    }

    /**
     * True when the competing categories are declared adjacent in the taxonomy.
     *
     * Adjacent competitors are the case the blueprint's differentiating
     * questions were written for. Non-adjacent competitors usually mean the
     * narrative is genuinely multi-dimensional rather than ambiguous.
     *
     * @param  array<int, string>  $slugs
     */
    public static function areAdjacent(array $slugs): bool
    {
        if (count($slugs) < 2) {
            return false;
        }

        $categories = VocationalCategory::whereIn('slug', $slugs)->get();

        foreach ($categories as $category) {
            $declared = $category->adjacent_categories['slugs'] ?? [];

            if (array_intersect($declared, $slugs) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize raw model output into ranked rows carrying a resolved slug.
     *
     * The model returns category *names*, which it may inflect ("Healing and
     * Care", "healing & care"). Matching on a normalized name avoids silently
     * dropping a score because of punctuation.
     *
     * @param  array<int, array{category?: string, score?: int|float}>  $categoryScores
     * @return Collection<int, array{slug: string, name: string, score: float}>
     */
    protected static function rank(array $categoryScores): Collection
    {
        $bySlug = VocationalCategory::all()->keyBy(
            fn (VocationalCategory $category) => static::normalize($category->name)
        );

        return collect($categoryScores)
            ->map(function (array $row) use ($bySlug) {
                $category = $bySlug->get(static::normalize($row['category'] ?? ''));

                if ($category === null) {
                    return null;
                }

                return [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'score' => (float) ($row['score'] ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values();
    }

    protected static function normalize(string $name): string
    {
        $name = str_replace('&', 'and', mb_strtolower($name));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name);
    }
}
