<?php

namespace App\Enums;

/**
 * Where an entry in the vocational brain came from.
 *
 * Attribution matters because the brain's whole promise is that it gives a
 * student *their own words* back. An entry whose origin is unknown cannot be
 * trusted to be theirs, and the payoff — a senior writing a college essay
 * asking "what have I said about why this matters to me?" — depends entirely
 * on that trust.
 */
enum BrainEntrySource: string
{
    /** The student chose to save this: "save this on my vocational brain." */
    case Direct = 'direct';

    /** Something the student said to the coach. */
    case Coach = 'coach';

    /** A reflection the student wrote on finishing an action. */
    case Action = 'action';

    /** An answer from an assessment. */
    case Assessment = 'assessment';

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
            self::Direct => 'You saved this',
            self::Coach => 'From a coaching conversation',
            self::Action => 'After you finished something',
            self::Assessment => 'From your assessment',
        };
    }
}
