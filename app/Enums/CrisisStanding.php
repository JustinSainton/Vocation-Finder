<?php

namespace App\Enums;

/**
 * Whether what somebody just wrote has to reach a person before it reaches the
 * engine.
 *
 * Two cases, deliberately. A graded scale — "mild concern", "moderate risk" —
 * would be a clinical judgement, and the safety invariant forbids this product
 * from making one. It is not qualified to say how bad it is. It is qualified
 * to stop talking about careers and hand over a phone number.
 */
enum CrisisStanding: string
{
    case None = 'none';

    /**
     * Set the vocational conversation aside and offer human support. Never a
     * diagnosis, never a report about the student to anybody else — a route to
     * a person, given to the student.
     */
    case Escalate = 'escalate';

    public function isEscalation(): bool
    {
        return $this === self::Escalate;
    }
}
