<?php

namespace App\Enums;

/**
 * The signal categories from blueprint 8.4 (Layer 4: Signal Detection).
 *
 * The blueprint is emphatic that these are not interchangeable: "Desire: 'I
 * love helping people understand things.' Skill: 'People often ask me to
 * explain things.' Burden: 'It bothers me when people are confused and no one
 * helps them.' ... These should not be treated as the same thing."
 *
 * Collapsing them is the failure mode this enum exists to prevent — an engine
 * that reads a desire as a demonstrated skill will name a direction the
 * student has never actually tested.
 */
enum SignalType: string
{
    case Desire = 'desire';
    case Burden = 'burden';
    case Strength = 'strength';
    case Skill = 'skill';
    case Interest = 'interest';
    case Value = 'value';
    case Environment = 'environment';
    case SocialOrientation = 'social_orientation';
    case ProblemOrientation = 'problem_orientation';
    case EnergySource = 'energy_source';
    case Constraint = 'constraint';
    case DevelopmentNeed = 'development_need';
    case Theme = 'theme';
    case Tension = 'tension';

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
            self::Desire => 'Desire',
            self::Burden => 'Burden',
            self::Strength => 'Strength',
            self::Skill => 'Skill',
            self::Interest => 'Interest',
            self::Value => 'Value',
            self::Environment => 'Work environment',
            self::SocialOrientation => 'Social orientation',
            self::ProblemOrientation => 'Problem orientation',
            self::EnergySource => 'Energy source',
            self::Constraint => 'Constraint',
            self::DevelopmentNeed => 'Development need',
            self::Theme => 'Repeated theme',
            self::Tension => 'Tension',
        };
    }

    /**
     * The weight class this signal carries, from blueprint 10.3.
     *
     * Burdens are the strongest signal class in the appendix engine rules;
     * desire indicates direction but never readiness on its own.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Burden, self::Skill, self::Strength => 1.0,
            self::Desire, self::Theme, self::Value => 0.75,
            self::Constraint, self::Tension, self::DevelopmentNeed => 0.5,
            default => 0.4,
        };
    }

    /**
     * Whether this signal type can, on its own, evidence demonstrated fit.
     *
     * Desires and interests cannot: blueprint 10.3 separates what a person
     * wants from what they have practised, endured, produced, or been trusted
     * to carry. Only the second kind counts as demonstrated.
     */
    public function canEvidenceDemonstratedFit(): bool
    {
        return in_array($this, [self::Skill, self::Strength, self::Burden, self::DevelopmentNeed], true);
    }
}
