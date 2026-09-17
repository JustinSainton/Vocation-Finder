<?php

namespace App\Enums;

/**
 * Where a student stands against a college's published admitted-student range.
 *
 * **Never a percentage.** The vision asks the tool to show a student "their
 * real chances of getting in", and every competitor renders that as a number:
 * a 34% chance, a 78% match, a dial that fills up. DESIGN.md forbids it, and
 * the reason is not aesthetic. A percentage invites a student to read a single
 * figure as a verdict on themselves, and admissions is not a computation — the
 * published GPA range is one input among essays, recommendations, context and
 * luck that no public dataset contains.
 *
 * So the standing is a word, and every word is a *description of a list*, not
 * a prediction about a person. A student is never told they will not get in.
 * They are told what kind of place this is for someone with their record, and
 * that a list of colleges needs some of each.
 *
 * The lowest case is {@see self::Reach} rather than anything like "unlikely",
 * for the reason {@see ReadinessLevel::Considering} is not "not ready": a
 * reach school is a normal and correct thing to apply to, and naming it as a
 * failure would quietly teach students to aim lower than they should.
 */
enum AdmissionStanding: string
{
    case Unknown = 'unknown';
    case Reach = 'reach';
    case Possible = 'possible';
    case Likely = 'likely';

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
            self::Unknown => 'Not enough to say',
            self::Reach => 'A reach',
            self::Possible => 'In range',
            self::Likely => 'Likely',
        };
    }

    /**
     * Always about the college and the list, never about the student.
     */
    public function description(): string
    {
        return match ($this) {
            self::Unknown => 'This school has not published a range we can compare anything to, or we do not have your GPA yet. That is not a no — it means the answer has to come from the admissions office.',
            self::Reach => 'Your GPA sits below the middle half of who they admitted last year. People get in from here every year, and a list with no reach on it is a list that was built to be safe.',
            self::Possible => 'Your GPA sits inside the middle half of who they admitted last year. This is the part of the list that does most of the work.',
            self::Likely => 'Your GPA sits above the middle half of who they admitted last year. Every list needs at least one of these, and it is not a consolation prize.',
        };
    }

    /**
     * What a list is missing when it has none of these.
     *
     * A student who applies only to reaches and a student who applies only to
     * likelies are both making the same mistake, and it is the one thing about
     * a college list that can actually be computed.
     */
    public function listAdvice(): string
    {
        return match ($this) {
            self::Unknown => 'Nothing on your list has a range we can read. Ask a counsellor to look at it with you.',
            self::Reach => 'Nothing on your list is a reach. Add one. You are allowed to want something you are not sure about.',
            self::Possible => 'Nothing on your list is in range. Most people end up at a school from this part of the list, so it should not be the empty part.',
            self::Likely => 'Nothing on your list is likely. Add one you would genuinely be glad to attend — not a fallback you would resent.',
        };
    }
}
