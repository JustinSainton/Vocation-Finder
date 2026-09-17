<?php

namespace App\Enums;

/**
 * The three things only the student can tell us.
 *
 * Blueprint §14 asks for perceived accuracy, usefulness and next-step clarity.
 * None of the three can be derived from the engine's own output: a narrative
 * can be internally coherent, provenance-clean and pass every lint while still
 * not sounding like the person it describes.
 *
 * Deliberately three. A fourth question measurably costs answers, and the
 * marginal metric is worth less than the responses it loses.
 */
enum FeedbackQuestion: string
{
    case SoundsLikeMe = 'sounds_like_me';
    case Useful = 'useful';
    case ClearNextStep = 'clear_next_step';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function prompt(): string
    {
        return match ($this) {
            self::SoundsLikeMe => 'Does this sound like you?',
            self::Useful => 'Was any of this useful?',
            self::ClearNextStep => 'Do you know what to do next?',
        };
    }

    /**
     * Why we ask, in the student's terms. Shown with the question, because a
     * student who cannot see what an answer is for gives the polite one.
     */
    public function reason(): string
    {
        return match ($this) {
            self::SoundsLikeMe => 'If it does not, that is worth knowing and it is not your fault.',
            self::Useful => 'Saying no here changes what we build next.',
            self::ClearNextStep => 'Knowing what to do next is the whole point of this.',
        };
    }
}
