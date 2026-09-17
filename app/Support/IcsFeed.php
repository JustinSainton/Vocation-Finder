<?php

namespace App\Support;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The plan, as a calendar a phone can subscribe to.
 *
 * The vision asks for syllabus dates on a student's calendar. Publishing the
 * *whole plan* instead of only coursework costs nothing and is strictly more
 * useful: a scholarship deadline and a problem set compete for the same
 * Thursday evening, and a student can only see that if both are in one place.
 *
 * Three properties this feed deliberately has:
 *
 * - **It is a mirror, never a notifier.** No VALARM is emitted. A tool that
 *   adds itself to somebody's lock screen at 8am has decided on their behalf
 *   that it gets to interrupt them; the nudge seam ({@see Nudges}) defaults to
 *   silence for the same reason, and a calendar the student subscribed to is
 *   already in front of them.
 * - **It is read-only and token-addressed.** Calendar clients cannot
 *   authenticate with a session, so the URL carries a rotatable secret, the
 *   same shape as the parent report. It is a feed of the student's own plan,
 *   which is why the student is the one who may rotate it.
 * - **Nothing interpretive crosses.** Titles and dates only: no readiness, no
 *   confidence, no category. A calendar entry is read by whoever glances at a
 *   shared screen, and the vocational reading of somebody's life is not a
 *   thing to leak onto a family iPad.
 */
class IcsFeed
{
    /**
     * Folding limit from RFC 5545 — 75 octets, not characters.
     */
    protected const FOLD_OCTETS = 73;

    /**
     * The student's feed secret, created on first use.
     *
     * Generated rather than derived from the user id: a token anybody could
     * compute from a primary key is not a secret.
     */
    public function tokenFor(User $student): string
    {
        if (! $student->calendar_token) {
            $student->forceFill(['calendar_token' => Str::random(64)])->save();
        }

        return (string) $student->calendar_token;
    }

    /**
     * Invalidate the old link by issuing a new one.
     */
    public function rotate(User $student): string
    {
        $student->forceFill(['calendar_token' => Str::random(64)])->save();

        return (string) $student->calendar_token;
    }

    /**
     * @param  Collection<int, Milestone>  $milestones
     */
    public function render(User $student, Collection $milestones): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Vocation Finder//Plan//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($this->calendarName($student)),
        ];

        foreach ($milestones as $milestone) {
            if ($milestone->due_on === null) {
                continue;
            }

            $lines = array_merge($lines, $this->event($milestone));
        }

        $lines[] = 'END:VCALENDAR';

        return collect($lines)
            ->flatMap(fn (string $line) => $this->fold($line))
            ->implode("\r\n")."\r\n";
    }

    /**
     * @return list<string>
     */
    protected function event(Milestone $milestone): array
    {
        $start = $milestone->due_on;

        return [
            'BEGIN:VEVENT',
            'UID:'.$milestone->id.'@vocationfinder',
            'DTSTAMP:'.($milestone->updated_at ?? now())->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$start->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape($milestone->title),
            'DESCRIPTION:'.$this->escape((string) $milestone->why),
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ];
    }

    protected function calendarName(User $student): string
    {
        return trim((string) strtok($student->name, ' ')).'’s plan';
    }

    /**
     * RFC 5545 text escaping. Backslash first, or it escapes its own output.
     */
    protected function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\;', '\\,'],
            $value,
        );
    }

    /**
     * Long lines must be split, and a split that lands inside a multi-byte
     * character produces a file some clients silently refuse to open.
     *
     * @return list<string>
     */
    protected function fold(string $line): array
    {
        if (strlen($line) <= self::FOLD_OCTETS) {
            return [$line];
        }

        $folded = [];
        $current = '';

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > self::FOLD_OCTETS) {
                $folded[] = $current;
                $current = ' ';
            }

            $current .= $character;
        }

        if ($current !== '' && $current !== ' ') {
            $folded[] = $current;
        }

        return $folded;
    }
}
