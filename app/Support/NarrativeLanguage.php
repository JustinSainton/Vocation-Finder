<?php

namespace App\Support;

/**
 * Which language a narrative came back in.
 *
 * The fixtures carry a locale and the engine is told it, but until this
 * existed nothing checked that the answer came back in the language the
 * student wrote in. That failure is silent and complete: a Spanish student
 * receives a fluent, correct, entirely English portrait, every other
 * assertion passes, and the run reports success.
 *
 * Deterministic on purpose. Asking a model which language a model just wrote
 * in is the same mistake as asking one for a value you can compute, and it
 * would fail in exactly the cases that matter.
 *
 * The method is function-word frequency, not grammar: the words a language
 * cannot avoid using are the ones a translation cannot hide. Weighted toward
 * markers that separate the confusable pair — Spanish and Portuguese share
 * most of their vocabulary, so `el`/`los`/`del`/`-ción` and
 * `você`/`não`/`da`/`-ção` do the work that `que` and `para` cannot.
 */
class NarrativeLanguage
{
    /**
     * @var array<string, array<int, string>>
     */
    protected const MARKERS = [
        'en' => [
            'the', 'and', 'you', 'your', 'that', 'with', 'what', 'this',
            'about', 'when', 'from', 'would', 'their', 'there', 'which',
        ],
        'es' => [
            'el', 'los', 'las', 'del', 'una', 'esto', 'eso', 'esa',
            'pero', 'cuando', 'porque', 'hiciste', 'dijiste', 'su', 'sus',
            'hablar', 'vale', 'nada', 'antes',
        ],
        'pt' => [
            'voce', 'nao', 'da', 'do', 'dos', 'das', 'isso', 'essa',
            'esse', 'mas', 'quando', 'porque', 'voces', 'seu', 'sua',
            'dela', 'dele', 'pelo', 'pela', 'sobre',
        ],
    ];

    /**
     * Suffixes worth more than a single function word because they are the
     * one place the two Romance languages reliably diverge in writing.
     *
     * @var array<string, array<int, string>>
     */
    protected const SUFFIXES = [
        'es' => ['cion', 'ciones', 'dad'],
        'pt' => ['cao', 'coes', 'dade'],
    ];

    protected const SUFFIX_WEIGHT = 3;

    public static function of(string $text): ?string
    {
        $normalized = ' '.trim(preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            static::fold(mb_strtolower($text)),
        ) ?? '').' ';

        $scores = [];

        foreach (static::MARKERS as $language => $markers) {
            $scores[$language] = 0;

            foreach ($markers as $marker) {
                $scores[$language] += substr_count($normalized, ' '.$marker.' ');
            }
        }

        foreach (static::SUFFIXES as $language => $suffixes) {
            foreach ($suffixes as $suffix) {
                $scores[$language] += static::SUFFIX_WEIGHT * substr_count($normalized, $suffix.' ');
            }
        }

        arsort($scores);

        $leader = array_key_first($scores);
        $top = $scores[$leader];
        $runnerUp = array_values($scores)[1] ?? 0;

        /*
         | A tie, or nothing to go on, returns null rather than guessing. A
         | confident wrong answer here would fail a live run for the wrong
         | reason, which is worse than declining to judge a two-line string.
         */
        return ($top > 0 && $top > $runnerUp) ? $leader : null;
    }

    /**
     * The language a locale asks for, in the same vocabulary `of()` returns.
     */
    public static function expectedFor(string $locale): string
    {
        return strtolower(explode('-', str_replace('_', '-', $locale))[0]);
    }

    /**
     * One character in, one character out — so counting is unaffected by
     * whether the model bothered with accents.
     */
    protected static function fold(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }
}
