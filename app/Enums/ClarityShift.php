<?php

namespace App\Enums;

/**
 * What happened between the two readings, for internal reporting only.
 *
 * {@see self::Unmeasured} is a case rather than a null so that a missing
 * reading cannot be quietly folded into {@see self::Unchanged}. Treating an
 * unanswered pair as "no change" is the most flattering possible error in the
 * one metric the blueprint singles out, and it would be invisible.
 */
enum ClarityShift: string
{
    case Unmeasured = 'unmeasured';
    case Clearer = 'clearer';
    case Unchanged = 'unchanged';
    case LessClear = 'less_clear';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Someone leaving less clear than they arrived is not noise to be averaged
     * out. It is the finding.
     */
    public function needsReading(): bool
    {
        return $this === self::LessClear;
    }
}
