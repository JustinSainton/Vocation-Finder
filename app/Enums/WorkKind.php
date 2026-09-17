<?php

namespace App\Enums;

/**
 * What kind of work a listing is.
 *
 * Apprenticeships are a first-class case rather than a job with a tag. The
 * vision pairs "jobs and apprenticeships" in one breath and the distinction
 * matters to a seventeen-year-old more than almost anything else on the
 * posting: an apprenticeship pays while it trains, which is the single fact
 * that makes a non-college path financially real to a family who assumes the
 * choice is college or nothing.
 */
enum WorkKind: string
{
    case Job = 'job';
    case Apprenticeship = 'apprenticeship';
    case Internship = 'internship';

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
            self::Job => 'Job',
            self::Apprenticeship => 'Apprenticeship',
            self::Internship => 'Internship',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Job => 'Paid work. You are hired to do the thing, and you learn by doing it.',
            self::Apprenticeship => 'You are paid while you are trained, and you finish with a credential somebody else recognises. This is not the lesser path.',
            self::Internship => 'A fixed stretch of time inside the work, usually alongside school. Ask whether it is paid before you commit to it.',
        };
    }
}
