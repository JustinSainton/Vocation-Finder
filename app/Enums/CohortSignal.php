<?php

namespace App\Enums;

/**
 * Why a particular student needs a person this week.
 *
 * This is deliberately not a grade, a standing or a rank. A cohort screen that
 * sorts teenagers by how well they are doing is a report card with better
 * typography, and `ReadinessLevel::rank()` already carries the sentence this
 * enum has to honour in code: ordering exists so a history can say "this
 * changed", not so anyone can be ranked against anyone else.
 *
 * So a student lands in exactly one bucket, and every bucket is a reason to
 * reach out rather than a verdict about them. {@see self::Moving} is listed
 * last and carries no detail on purpose — "nothing needed from you" is the
 * correct amount of information about a student who is fine.
 */
enum CohortSignal: string
{
    case NeedsConsent = 'needs_consent';
    case NotStarted = 'not_started';
    case Blocked = 'blocked';
    case Stalled = 'stalled';
    case Moving = 'moving';

    /**
     * Evaluation order, and the order the page reads in.
     *
     * The earlier a signal sits, the more it is the binding constraint: a
     * student waiting on a parent cannot use the coach at all, so nothing
     * further down is the thing to fix first.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::NeedsConsent, self::NotStarted, self::Blocked, self::Stalled, self::Moving];
    }

    public function label(): string
    {
        return match ($this) {
            self::NeedsConsent => 'Waiting on a parent',
            self::NotStarted => 'Has not started',
            self::Blocked => 'Named something you could fix',
            self::Stalled => 'Same step for a while',
            self::Moving => 'Working',
        };
    }

    /**
     * What to actually do — one move, in the second person, addressed to the
     * adult reading the page.
     *
     * A flag with no move attached is a way of feeling informed. The plan
     * surface already refuses to show a student a closed window without
     * telling them what to do about it; a counsellor gets the same courtesy.
     */
    public function move(): string
    {
        return match ($this) {
            self::NeedsConsent => 'Call a parent. No consent is on file yet, and a phone call closes this faster than another email does.',
            self::NotStarted => 'Ask what stopped them. It is almost always time, not willingness.',
            self::Blocked => 'Ask what it would take. They have named something a school can actually do something about.',
            self::Stalled => 'Ask what is in the way of the step, not whether they have done it yet.',
            self::Moving => 'Nothing needed from you this week.',
        };
    }

    /**
     * Whether this signal is a reason to spend the week's attention here.
     */
    public function needsSomebody(): bool
    {
        return $this !== self::Moving;
    }
}
