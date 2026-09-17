<?php

namespace App\Enums;

/**
 * The kinds of milestone the vision names, closed as an enum for the same
 * reason every vocabulary here is closed: an open string field fills up with
 * seventeen spellings of "internship" and then nothing can be counted or
 * sorted.
 */
enum MilestoneKind: string
{
    case Test = 'test';
    case Application = 'application';
    case Academic = 'academic';
    case Work = 'work';
    case Money = 'money';
    case Build = 'build';
    case Passage = 'passage';

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
            self::Test => 'Test',
            self::Application => 'Application',
            self::Academic => 'School',
            self::Work => 'Work',
            self::Money => 'Money',
            self::Build => 'Something you make',
            self::Passage => 'Happens anyway',
        };
    }

    /**
     * Whether this kind is something the student *does*, or something that
     * arrives on its own.
     *
     * {@see self::Passage} covers finishing a year and graduating: they are
     * facts of the calendar, computed rather than prescribed, and the plan
     * must never present one as a task. A student cannot be behind on turning
     * seventeen.
     */
    public function isAchieved(): bool
    {
        return $this !== self::Passage;
    }
}
