<?php

namespace App\Support;

/**
 * How much somebody actually wrote, in any language they wrote it in.
 *
 * PHP's `str_word_count()` is byte-oriented and locale-dependent, and the
 * consequences here are not cosmetic. Every place this product asks "was this
 * answer substantial enough to conclude anything from" ran through it, so a
 * student answering in Chinese, Japanese or Thai got whichever of two wrong
 * answers the machine's locale happened to produce:
 *
 * - In the C locale, **zero words**, because no byte of the answer counts as
 *   a letter. Such a student was permanently capped at "not enough evidence"
 *   no matter what they said, and nothing in the product would have reported
 *   it as a failure — it looks exactly like a student who typed nothing.
 * - In a locale where the high bytes are alphabetic, **one word per UTF-8
 *   byte**: three times too generous, and the same student's ceiling now
 *   depends on the server they happened to land on.
 *
 * Accented Latin text is counted correctly by either, which is why this was
 * easy to miss: the languages closest to the developer's own read fine.
 *
 * Found by the bias pass in roadmap 5.4, which is the point of running one:
 * the engine was fair in every rule anybody wrote and unfair in the utility
 * function underneath them.
 *
 * The count is a unit of writing rather than a lexical word. Space-delimited
 * scripts count runs of letters and digits; scripts that do not put spaces
 * between words count characters, because there is no honest way to count
 * "words" in 我喜欢修理东西 without a dictionary, and one character is a closer
 * unit of effort than one sentence.
 */
class WordCount
{
    /**
     * Characters from scripts that do not separate words with spaces.
     *
     * Han (Chinese and kanji), Hiragana, Katakana, Thai, Khmer, Lao and
     * Burmese. Korean is deliberately absent: Hangul is space-delimited.
     */
    protected const UNSPACED_SCRIPTS = '\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}'
        .'\x{0E00}-\x{0E7F}\x{1780}-\x{17FF}\x{0E80}-\x{0EFF}\x{1000}-\x{109F}';

    public static function of(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        $unspaced = preg_match_all('/['.self::UNSPACED_SCRIPTS.']/u', $text) ?: 0;

        $spaced = preg_match_all(
            '/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u',
            preg_replace('/['.self::UNSPACED_SCRIPTS.']/u', ' ', $text) ?? $text,
        ) ?: 0;

        return $spaced + $unspaced;
    }
}
