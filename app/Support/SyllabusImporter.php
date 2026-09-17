<?php

namespace App\Support;

use App\Ai\Agents\SyllabusParser;
use App\Enums\MilestoneKind;
use App\Models\Milestone;
use App\Models\Syllabus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turn an uploaded syllabus into dated milestones, discarding anything that
 * cannot be traced back to the document.
 *
 * Two decisions shape this class.
 *
 * **Assignments are milestones, not a new kind of object.** The plan already
 * is "dated outcomes in sections" ({@see Milestone}), so a parallel assignment
 * list would be a second plan competing with the first — and the student would
 * have to decide which one to believe. Coursework arrives in the plan they
 * already read, marked {@see MilestoneKind::Academic} and carrying the
 * syllabus it came from.
 *
 * **The model points, the code computes.** This is the verbatim-provenance
 * discipline from Layer 4 ({@see SignalExtractor::spanAppearsIn()}) applied to
 * a calendar: an assignment whose title does not appear in the syllabus is a
 * hallucinated deadline and is dropped by substring check rather than by
 * judgement, and the date is parsed here from the span the syllabus printed
 * rather than accepted as a number the model made up. Nothing discarded is
 * thrown away silently — the rejects are written to `syllabi.discarded`, so a
 * syllabus format we parse badly is visible rather than merely quiet.
 */
class SyllabusImporter
{
    /**
     * How far before the term's anchor date a bare "Oct 14" may fall before we
     * read it as belonging to the following year.
     *
     * A spring assignment on an autumn-dated syllabus is the ordinary case;
     * a syllabus handed out a fortnight after term began is also ordinary.
     */
    protected const ANCHOR_GRACE_DAYS = 30;

    /**
     * Parse a syllabus with the model and store what survives.
     *
     * @return Collection<int, Milestone>
     */
    public function import(Syllabus $syllabus): Collection
    {
        $agent = new SyllabusParser((string) $syllabus->source_text, $syllabus->course_code);
        $response = $agent->prompt($agent->buildPrompt());

        return $this->store($syllabus, $response->structured['assignments'] ?? []);
    }

    /**
     * Validate and store already-emitted assignments.
     *
     * Separate from {@see self::import()} so every rule below is testable
     * without a model in the loop — including the rules that exist precisely
     * for when the model misbehaves.
     *
     * @param  array<int, array<string, mixed>>  $assignments
     * @return Collection<int, Milestone>
     */
    public function store(Syllabus $syllabus, array $assignments): Collection
    {
        $source = (string) $syllabus->source_text;
        $anchor = $this->anchor($syllabus);

        $kept = collect();
        $discarded = [];

        foreach ($assignments as $assignment) {
            $title = trim((string) ($assignment['title'] ?? ''));
            $dueText = trim((string) ($assignment['due_text'] ?? ''));

            $reason = $this->reject($title, $dueText, $source);

            if ($reason === null) {
                $due = $this->dueDate($dueText, $anchor);
                $reason = $due === null ? 'date_not_understood' : null;
            }

            if ($reason !== null) {
                $discarded[] = ['title' => $title, 'due_text' => $dueText, 'reason' => $reason];

                continue;
            }

            $kept->push($this->milestone($syllabus, $title, $due));
        }

        $syllabus->forceFill([
            'parsed_at' => now(),
            'discarded' => $discarded,
        ])->save();

        return $kept;
    }

    /**
     * Why this assignment cannot be trusted, or null if it can.
     *
     * The order matters only for the message; any one of these is fatal.
     */
    protected function reject(string $title, string $dueText, string $source): ?string
    {
        return match (true) {
            $title === '' => 'no_title',
            $dueText === '' => 'no_date',
            ! SignalExtractor::spanAppearsIn($title, $source) => 'title_not_in_syllabus',
            ! SignalExtractor::spanAppearsIn($dueText, $source) => 'date_not_in_syllabus',
            default => null,
        };
    }

    /**
     * Create the milestone, or return the one already there.
     *
     * Re-parsing a syllabus must never duplicate a student's coursework and
     * must never erase what they have already marked done, so this matches on
     * the syllabus and the title rather than clearing and rewriting.
     */
    protected function milestone(Syllabus $syllabus, string $title, CarbonImmutable $due): Milestone
    {
        $course = $syllabus->course_code ?: $syllabus->course_title;

        return Milestone::firstOrCreate(
            ['syllabus_id' => $syllabus->id, 'title' => $title],
            [
                'user_id' => $syllabus->user_id,
                'kind' => MilestoneKind::Academic,
                'due_on' => $due,
                'why' => $course
                    ? "This is on the syllabus for {$course}."
                    : 'This is on a syllabus you uploaded.',
            ],
        );
    }

    /**
     * The date the term is measured from.
     */
    protected function anchor(Syllabus $syllabus): CarbonImmutable
    {
        $anchor = $syllabus->enrollment?->started_on
            ?? $syllabus->created_at
            ?? CarbonImmutable::now();

        return CarbonImmutable::parse($anchor)->startOfDay();
    }

    /**
     * Read a calendar date out of the span the syllabus printed.
     *
     * Syllabi write dates as "Friday, Oct 14", "10/14", "Week 6 — Nov 3". We
     * pull the date phrase out of whatever surrounds it and parse that, which
     * is why the model was asked for the printed span rather than for a date:
     * a span can be checked against the document, and a date cannot.
     *
     * A year is almost never printed. A bare month and day is read as this
     * term's, rolling into the next year when it falls before the term began —
     * the spring half of an autumn-dated syllabus.
     */
    protected function dueDate(string $text, CarbonImmutable $anchor): ?CarbonImmutable
    {
        $phrase = $this->datePhrase($text);

        if ($phrase === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($phrase)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if (preg_match('/\d{4}/', $phrase) === 1) {
            return $date;
        }

        $date = $date->setYear($anchor->year);

        return $date->lt($anchor->subDays(self::ANCHOR_GRACE_DAYS))
            ? $date->addYear()
            : $date;
    }

    /**
     * The date-looking fragment of a printed span, normalised just enough to
     * parse and not one bit further.
     */
    protected function datePhrase(string $text): ?string
    {
        $text = str_ireplace(['Sept.', 'Sept '], ['Sep ', 'Sep '], $text);

        $patterns = [
            '#\b\d{1,2}/\d{1,2}(?:/\d{2,4})?\b#',
            '#\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?(?:,?\s*\d{4})?#i',
            '#\b\d{1,2}(?:st|nd|rd|th)?\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?(?:,?\s*\d{4})?#i',
            '#\b\d{4}-\d{2}-\d{2}\b#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return str_replace('.', '', $matches[0]);
            }
        }

        return null;
    }
}
