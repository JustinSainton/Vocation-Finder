<?php

namespace App\Support;

use RuntimeException;

/**
 * Raised when Layer 8 finds language the product must never show a student.
 *
 * Distinct from a format failure so the retry can hand the model a correction
 * about its *language* rather than about its markdown headers. Telling a model
 * its headings were wrong when it actually claimed to speak for God produces
 * a well-formatted second draft with the same violation in it.
 */
class RedTeamViolation extends RuntimeException
{
    /**
     * @param  array<int, array{rule: string, severity: string, match: string}>  $findings
     */
    public function __construct(public readonly array $findings)
    {
        $rules = collect($findings)->pluck('rule')->unique()->implode(', ');

        parent::__construct("Narrative failed the red-team pass: {$rules}");
    }
}
