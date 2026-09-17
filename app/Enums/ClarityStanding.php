<?php

namespace App\Enums;

/**
 * How clear somebody says they are, in words.
 *
 * Words rather than a scale, for the reason {@see FeedbackStanding} gives: a
 * scale produces an average and an average produces a dial. These four are
 * also genuinely different states rather than degrees of one — "I have a few
 * options" and "I know what I want to try next" are not 3 and 4 of the same
 * thing, they are the difference between having a list and having a Tuesday.
 *
 * {@see self::rank()} is internal, for comparing the two readings. It is never
 * shown, averaged or sent anywhere near a student: being told you got 40%
 * clearer is being graded on your own feelings.
 */
enum ClarityStanding: string
{
    case NoIdea = 'no_idea';
    case VagueSense = 'vague_sense';
    case FewOptions = 'few_options';
    case KnowWhatToTry = 'know_what_to_try';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::NoIdea => 'No idea at all',
            self::VagueSense => 'A vague sense',
            self::FewOptions => 'A few options in mind',
            self::KnowWhatToTry => 'I know what I want to try next',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::NoIdea => 0,
            self::VagueSense => 1,
            self::FewOptions => 2,
            self::KnowWhatToTry => 3,
        };
    }
}
