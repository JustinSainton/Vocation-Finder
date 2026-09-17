<?php

namespace App\Support;

use App\Models\VocationalCategory;

/**
 * Which engine produced a given result.
 *
 * Roadmap 0.4. An evaluation log that cannot say *which* engine produced the
 * row is a record of an anecdote: it can tell you that a narrative was weak
 * and never whether the prompt you changed last Tuesday is why.
 *
 * All three versions are **derived, never declared.** A hand-maintained
 * `PROMPT_VERSION = 3` is a promise a developer has to keep on every edit, and
 * the one time it is forgotten is the one time the data matters — every row
 * after that is silently attributed to the wrong prompt, and the logs are
 * worse than none because they are confidently wrong. Hashing the thing itself
 * cannot drift from the thing itself.
 *
 * Never ask a model, or a human, for a value you can compute.
 */
class EngineVersion
{
    /**
     * Files whose contents *are* the interpretive prompt.
     *
     * Hashing the source rather than a rendered prompt means no agent has to
     * be constructed — rendering most of these requires an assessment — and an
     * edit anywhere in the file is caught, including the guardrails and the
     * few-shot examples, which shape output exactly as much as the questions.
     *
     * @var list<string>
     */
    protected const PROMPT_SOURCES = [
        'app/Ai/Agents/SignalDetection.php',
        'app/Ai/Agents/VocationalAnalysis.php',
        'app/Ai/Agents/CategoryDisambiguation.php',
        'app/Ai/Agents/NarrativeSynthesis.php',
        'app/Support/TaxonomyPrompt.php',
        'app/Support/RedTeamLint.php',
    ];

    /**
     * The columns that govern interpretation. A typo fixed in a description
     * changes what the model is told, so it changes the taxonomy version.
     *
     * @var list<string>
     */
    protected const TAXONOMY_COLUMNS = [
        'slug', 'name', 'description', 'core_function', 'summary_sentence',
        'signal_fingerprint', 'distortions', 'adjacent_categories', 'taxonomy_profile',
    ];

    public static function model(): string
    {
        return (string) config('vocation.ai.model');
    }

    public static function prompt(): string
    {
        $fingerprint = '';

        foreach (static::PROMPT_SOURCES as $source) {
            $path = base_path($source);

            $fingerprint .= $source.':'.(is_file($path) ? (string) md5_file($path) : 'missing')."\n";
        }

        return static::shorten($fingerprint);
    }

    /**
     * Read from the database rather than a seeder, because the taxonomy the
     * engine actually used is whatever is in the table at the time — an
     * unrun seeder or a hand-edited row would otherwise be invisible.
     */
    public static function taxonomy(): string
    {
        $rows = VocationalCategory::query()
            ->orderBy('slug')
            ->get(static::TAXONOMY_COLUMNS)
            ->map(fn (VocationalCategory $category) => collect(static::TAXONOMY_COLUMNS)
                ->map(fn (string $column) => json_encode($category->getAttribute($column)))
                ->implode('|'))
            ->implode("\n");

        return static::shorten($rows);
    }

    /**
     * @return array{model_version: string, prompt_version: string, taxonomy_version: string}
     */
    public static function all(): array
    {
        return [
            'model_version' => static::model(),
            'prompt_version' => static::prompt(),
            'taxonomy_version' => static::taxonomy(),
        ];
    }

    /**
     * Twelve hex characters: long enough that two real versions will not
     * collide, short enough to read in a log line and compare by eye.
     */
    protected static function shorten(string $material): string
    {
        return substr(hash('sha256', $material), 0, 12);
    }
}
