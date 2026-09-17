<?php

namespace App\Enums;

/**
 * The nine things a human reads a portrait for.
 *
 * Blueprint §14 names them: accuracy, specificity, humility, actionability,
 * theological alignment, psychological safety, category fit, explanation
 * quality, and avoidance of overclaiming. They are here as an enum rather than
 * a review form's field names so that the list cannot quietly shrink to the
 * ones that are easy to answer — and every one of them is phrased as a claim
 * that either **holds or does not**, so a failure always reads the same way.
 *
 * Overclaiming in particular is written as "stops short of claiming more than
 * the evidence carries" for that reason. A dimension where "yes" is the bad
 * answer gets scored backwards by a tired reviewer at least once.
 */
enum ReviewDimension: string
{
    case Accuracy = 'accuracy';
    case Specificity = 'specificity';
    case Humility = 'humility';
    case Actionability = 'actionability';
    case TheologicalAlignment = 'theological_alignment';
    case PsychologicalSafety = 'psychological_safety';
    case CategoryFit = 'category_fit';
    case ExplanationQuality = 'explanation_quality';
    case NoOverclaiming = 'no_overclaiming';

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
            self::Accuracy => 'Accuracy',
            self::Specificity => 'Specificity',
            self::Humility => 'Humility',
            self::Actionability => 'Actionability',
            self::TheologicalAlignment => 'Theological alignment',
            self::PsychologicalSafety => 'Psychological safety',
            self::CategoryFit => 'Category fit',
            self::ExplanationQuality => 'Explanation quality',
            self::NoOverclaiming => 'Stays inside the evidence',
        };
    }

    /**
     * What the reviewer is actually being asked, in a form that can only be
     * answered by reading this student's answers beside this portrait.
     */
    public function question(): string
    {
        return match ($this) {
            self::Accuracy => 'Is every claim here traceable to something they actually said?',
            self::Specificity => 'Could this paragraph have been written about somebody else?',
            self::Humility => 'Does it hold its conclusions loosely enough to be wrong?',
            self::Actionability => 'Is there something they could do this week because of this?',
            self::TheologicalAlignment => 'Does it treat calling as something to be discerned rather than announced?',
            self::PsychologicalSafety => 'Would reading this at a bad moment make things worse?',
            self::CategoryFit => 'Do the pathways follow from the evidence, rather than from the language they used?',
            self::ExplanationQuality => 'Would they understand why the engine said this?',
            self::NoOverclaiming => 'Does it stop short of claiming more than the evidence carries?',
        };
    }
}
