<?php

namespace App\Enums;

/**
 * The six gaps that sit between a student and their next step.
 *
 * From the vision document: the refinement conversation exists to locate
 * these. Most students cannot name which one is theirs, and naming it is most
 * of the help — a student who believes they lack information, when what they
 * actually lack is access to anyone doing the work, will read more and move
 * no closer.
 *
 * A gap is a thing to close, never a deficiency in the person. The wording
 * here is deliberately about circumstances rather than character.
 */
enum GapType: string
{
    case Information = 'information';
    case Access = 'access';
    case Finances = 'finances';
    case Habits = 'habits';
    case Relationships = 'relationships';
    case FutureOutlook = 'future_outlook';

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
            self::Information => 'Information',
            self::Access => 'Access',
            self::Finances => 'Finances',
            self::Habits => 'Habits',
            self::Relationships => 'Relationships',
            self::FutureOutlook => 'Future outlook',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Information => 'They do not yet know what the work is actually like day to day.',
            self::Access => 'They cannot reach the people, places or programmes the path runs through.',
            self::Finances => 'Cost stands in the way — or an assumption about cost that may not be true.',
            self::Habits => 'The daily practice the path asks for is not in place yet.',
            self::Relationships => 'Nobody in their life does this work, so there is no one to ask.',
            self::FutureOutlook => 'They cannot picture themselves in it at all.',
        };
    }

    /**
     * The kind of step that actually closes this gap.
     *
     * Blueprint 10.5 lists nine testable next-step forms, and they are not
     * interchangeable. Reading an article does not close an access gap and
     * meeting someone does not close a habits gap, so a gap's type has to
     * constrain what counts as progress on it.
     */
    public function closingMove(): string
    {
        return match ($this) {
            self::Information => 'Ask someone who does the work what it is actually like, or spend time where it happens.',
            self::Access => 'Find one concrete way in — a programme, a shift, a volunteer slot, an open door.',
            self::Finances => 'Find out what it actually costs and what help exists, before deciding it is out of reach.',
            self::Habits => 'Practise the smallest real version of the work on a schedule they can keep.',
            self::Relationships => 'Meet one person who does this work, and stay in touch with them.',
            self::FutureOutlook => 'Get close enough to picture it — shadow someone, visit, or try a small piece of it.',
        };
    }
}
