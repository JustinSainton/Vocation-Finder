<?php

namespace App\Enums;

/**
 * How an engine run ended. Two values, because a run either produced a result
 * a student can read or it did not, and every shade in between is a lie told
 * to make a dashboard look healthier.
 */
enum EvaluationOutcome: string
{
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Produced a result',
            self::Failed => 'Produced nothing',
        };
    }
}
