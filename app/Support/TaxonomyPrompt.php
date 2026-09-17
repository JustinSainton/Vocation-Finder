<?php

namespace App\Support;

use App\Models\VocationalCategory;
use Illuminate\Support\Collection;

/**
 * Renders the governing taxonomy (blueprint Appendix 10.6) into prompt text.
 *
 * The blueprint specifies two layers and they are not interchangeable:
 *
 * - **Short** is the classification anchor — used for fast matching of
 *   narrative signals against all 17 pathways.
 * - **Expanded** is interpretive nuance — used to resolve ambiguity, weigh
 *   competing signals, and tell a healthy orientation from a distorted one.
 *
 * Rendering every expanded layer for all 17 categories on every call is both
 * expensive and counterproductive: it buries the anchor in prose. So the
 * classification pass gets the short layer for all categories, and the
 * disambiguation pass gets the expanded layer for a shortlist only.
 */
class TaxonomyPrompt
{
    /**
     * The canonical category slugs, in blueprint order.
     *
     * Read from the taxonomy data file rather than the database so a schema
     * can be built without a seeded connection, and so there is exactly one
     * place a slug is ever defined.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        static $slugs = null;

        return $slugs ??= array_values(array_map(
            static fn (array $entry): string => $entry['slug'],
            require database_path('seeders/data/vocational_taxonomy.php'),
        ));
    }

    /**
     * The canonical category names, in taxonomy order.
     *
     * The seventeen names are a closed vocabulary and the analysis pass is
     * asked to return them verbatim, so the schema can close it. Read from the
     * data file for the same reason {@see slugs()} is — a schema must build
     * without a seeded connection, and a category is defined in exactly one
     * place.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $entries = require database_path('seeders/data/vocational_taxonomy.php');

        usort($entries, static fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);

        return $names = array_values(array_map(
            static fn (array $entry): string => $entry['name'],
            $entries,
        ));
    }

    /**
     * The canonical names of the given slugs, in taxonomy order.
     *
     * Unknown slugs are dropped rather than guessed at: a schema built around
     * a name the taxonomy does not contain would constrain the model to an
     * answer nothing downstream can resolve.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public static function namesFor(array $slugs): array
    {
        $entries = require database_path('seeders/data/vocational_taxonomy.php');

        usort($entries, static fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);

        return array_values(array_map(
            static fn (array $entry): string => $entry['name'],
            array_filter($entries, static fn (array $entry): bool => in_array($entry['slug'], $slugs, true)),
        ));
    }

    /**
     * A one-line index of the taxonomy: slug, name and core function.
     *
     * For prompts that need to *name* a category rather than interpret one —
     * job classification, for instance. Read from the data file for the same
     * reason {@see slugs()} is: it is the one place a category is defined,
     * and a hand-written copy of this list in a prompt is a copy that will
     * still say seventeen things after the taxonomy says eighteen.
     */
    public static function index(): string
    {
        $entries = require database_path('seeders/data/vocational_taxonomy.php');

        usort($entries, static fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);

        return collect($entries)
            ->map(static fn (array $entry): string => sprintf(
                '- %s: %s — %s',
                $entry['slug'],
                $entry['name'],
                $entry['core_function'],
            ))
            ->implode("\n");
    }

    /**
     * The classification anchor: every category, short layer only.
     */
    public static function anchors(): string
    {
        $categories = static::categories();

        if ($categories->isEmpty()) {
            return '';
        }

        $rendered = $categories->map(function (VocationalCategory $category) {
            $fingerprint = $category->signal_fingerprint ?? [];

            $lines = [
                "### {$category->sort_order}. {$category->name}",
                "Core function: {$category->core_function}",
            ];

            foreach (['desires' => 'Desires', 'burdens' => 'Burdens', 'strengths' => 'Strengths'] as $key => $label) {
                if (filled($fingerprint[$key] ?? [])) {
                    $lines[] = "{$label}: ".implode('; ', $fingerprint[$key]);
                }
            }

            if (filled($phrases = $category->literalSignalPhrases())) {
                $quoted = collect($phrases)->map(fn (string $phrase) => "\"{$phrase}\"")->implode(', ');
                $lines[] = "Literal signal phrases: {$quoted}";
            }

            if (filled($category->distortions)) {
                $lines[] = 'Distortions: '.implode('; ', $category->distortions);
            }

            return implode("\n", $lines);
        });

        return $rendered->implode("\n\n");
    }

    /**
     * Interpretive nuance for a shortlist of competing categories, including
     * the differentiating questions that separate them from their neighbours.
     *
     * @param  array<int, string>  $slugs
     */
    public static function disambiguation(array $slugs): string
    {
        if ($slugs === []) {
            return '';
        }

        $categories = static::categories()->whereIn('slug', $slugs);

        if ($categories->isEmpty()) {
            return '';
        }

        return $categories->map(function (VocationalCategory $category) {
            $profile = $category->taxonomy_profile ?? [];

            $lines = ["### {$category->name}"];

            foreach ([1 => 'Meaning', 4 => 'Desires', 5 => 'Burdens', 8 => 'Distortions'] as $question => $label) {
                if (filled($expanded = $profile["q{$question}"]['expanded'] ?? null)) {
                    $lines[] = "{$label}: {$expanded}";
                }
            }

            if (filled($questions = $category->differentiatingQuestions())) {
                $lines[] = 'Ask yourself: '.implode(' ', $questions);
            }

            return implode("\n", $lines);
        })->implode("\n\n");
    }

    /**
     * The Q10 sentence for a category, pre-approved verbatim by the founder.
     *
     * Returned for direct use. It must never be paraphrased, regenerated, or
     * passed to a model for rewriting.
     */
    public static function summarySentence(string $slug): ?string
    {
        return static::categories()->firstWhere('slug', $slug)?->summary_sentence;
    }

    /**
     * @return Collection<int, VocationalCategory>
     */
    protected static function categories(): Collection
    {
        return VocationalCategory::query()->orderBy('sort_order')->get();
    }
}
