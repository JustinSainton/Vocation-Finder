<?php

namespace App\Enums;

/**
 * Where a student is in the first-run sequence.
 *
 * The order is the vision's, and the last one is the point: "if a student
 * finishes their first session without one concrete thing to do, we haven't
 * proven anything."
 */
enum FirstRunStep: string
{
    case Assessment = 'assessment';
    case Account = 'account';
    case Results = 'results';
    case Portrait = 'portrait';
    case ParentConsent = 'parent_consent';
    case Checkout = 'checkout';
    case Refinement = 'refinement';
    case FirstAction = 'first_action';
    case Complete = 'complete';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * What the student is told is next. Never a status label — a next move.
     */
    public function prompt(): string
    {
        return match ($this) {
            self::Assessment => 'Start with the questions. There are twenty, and there are no wrong answers to any of them.',
            self::Account => 'Save your answers to your own account so nothing you wrote is lost.',
            self::Results => 'Your answers are being read. This takes a minute.',
            self::Portrait => 'This is yours to keep. Come back junior year and we will go further with it.',
            self::ParentConsent => 'Ask a parent or guardian to say yes, and we will take it from there.',
            self::Checkout => 'Open the coach and the brain.',
            self::Refinement => 'Talk to your coach. It has read everything you wrote and it wants to fill in what the questions could not ask.',
            self::FirstAction => 'Find the one thing to do next.',
            self::Complete => 'You have one thing to do. Come back and tell your coach how it went.',
        };
    }

    /**
     * Whether the sequence stops here rather than continuing.
     *
     * A freshman is not blocked, stalled or waiting for anything — the portrait
     * is the whole product for them this year. Treating that as an incomplete
     * funnel would push a fourteen-year-old toward a decision the blueprint
     * says is the central developmental risk.
     */
    public function isTerminal(): bool
    {
        return $this === self::Portrait || $this === self::Complete;
    }
}
