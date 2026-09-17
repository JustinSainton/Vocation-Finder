<?php

namespace App\Enums;

use App\Support\ConfidenceCalculator;

/**
 * The five confidence levels from blueprint 8.6, in descending order.
 *
 * The blueprint is explicit that these are internal structure, not a verdict:
 * "Scoring should not be presented as destiny. It should be used internally to
 * structure the output." A level is never rendered as a percentage, a dial, or
 * a match score, and it is never colour-coded.
 *
 * A level is always *derived* — see {@see ConfidenceCalculator}.
 * The model is never asked how confident it is, because a model asked that
 * question reports fluency rather than evidence.
 */
enum ConfidenceLevel: string
{
    case Strong = 'strong';
    case Moderate = 'moderate';
    case Emerging = 'emerging';
    case Weak = 'weak';
    case InsufficientEvidence = 'insufficient_evidence';

    /**
     * Descending order, strongest first.
     *
     * @return list<self>
     */
    public static function descending(): array
    {
        return [
            self::Strong,
            self::Moderate,
            self::Emerging,
            self::Weak,
            self::InsufficientEvidence,
        ];
    }

    /**
     * Rank, where 0 is the strongest. Used for capping, not for display.
     */
    public function rank(): int
    {
        return array_search($this, self::descending(), true);
    }

    /**
     * The blueprint's own wording for this level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Strong => 'Strong signal',
            self::Moderate => 'Moderate signal',
            self::Emerging => 'Emerging signal',
            self::Weak => 'Weak signal',
            self::InsufficientEvidence => 'Insufficient evidence',
        };
    }

    /**
     * Whether the system may name a vocational direction at this level.
     *
     * Below Moderate the blueprint forbids forcing a conclusion: the result
     * must instead diagnose why confidence is low and drive toward a testable
     * next step.
     */
    public function permitsConclusion(): bool
    {
        return $this->rank() <= self::Moderate->rank();
    }

    /**
     * Whether low-confidence mode applies.
     */
    public function isLowConfidence(): bool
    {
        return ! $this->permitsConclusion();
    }

    /**
     * The weaker of this level and the given ceiling.
     *
     * Capping is how thin evidence is prevented from producing a confident
     * result: a high score on two sentences is still not a strong signal.
     */
    public function cappedAt(self $ceiling): self
    {
        return $this->rank() >= $ceiling->rank() ? $this : $ceiling;
    }
}
