<?php

namespace App\Enums;

use App\Http\Controllers\Web\ClarityCheckController;

/**
 * When a clarity reading was taken.
 *
 * The blueprint calls clarity measured before and after "one of the clearest
 * indicators of usefulness", and the word *before* is load-bearing. A single
 * reading taken at the end measures how somebody feels about a nice piece of
 * writing. Two readings measure whether anything moved, which is the only
 * version of this question worth asking — and it is why
 * {@see ClarityCheckController} refuses a "before"
 * on an assessment that already has answers. A before-reading taken afterwards
 * is a memory of a feeling, and people remember being more lost than they were.
 */
enum ClarityMoment: string
{
    case Before = 'before';
    case After = 'after';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The question, asked the same way at both ends.
     *
     * Identical wording on purpose: a question reworded between the two
     * readings measures the rewording.
     */
    public function prompt(): string
    {
        return 'Right now, how clear are you about what to do next?';
    }
}
