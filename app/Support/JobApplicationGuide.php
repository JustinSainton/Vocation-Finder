<?php

namespace App\Support;

use App\Enums\WorkKind;
use App\Models\JobListing;
use App\Models\User;

/**
 * How to apply for one job, step by step — and never applying.
 *
 * The same boundary {@see CollegeApplication} holds. The vision says the tool
 * "guides the student on how to apply — best practices based on public
 * information about that company's hiring process", and every step below is
 * something the student does with their own hands.
 *
 * Two things here are specific to putting a minor in front of an employer, and
 * neither is optional:
 *
 * - **The work permit.** Most US states require an employment certificate for
 *   under-eighteens, issued by the school, and a student who learns about it
 *   on their first day has already lost the job. It is a step, dated before
 *   the others, because it involves a third party who works school hours.
 * - **Who knows where you are going.** An interview is a meeting with a
 *   stranger at an address. Telling somebody first is ordinary safeguarding,
 *   and a tool that sends teenagers to interviews without saying it has
 *   skipped the one piece of advice it was uniquely placed to give.
 */
class JobApplicationGuide
{
    /**
     * @return list<array{title: string, detail: string, url: ?string, yours: bool}>
     */
    public function steps(User $student, JobListing $listing): array
    {
        $steps = [];

        if ($this->isMinor($student)) {
            $steps[] = [
                'title' => 'Get your work permit first',
                'detail' => 'Most states require an employment certificate if you are under 18, and your school issues it. Ask the front office this week — it goes through an adult\'s desk, not a website, and that takes longer than you think.',
                'url' => null,
                'yours' => true,
            ];
        }

        $steps[] = [
            'title' => 'Read the posting twice and write down what they actually need',
            'detail' => 'Not to memorise it. So that the sentence you send them answers the thing they asked for, which is what almost nobody does.',
            'url' => $listing->source_url,
            'yours' => true,
        ];

        $steps[] = [
            'title' => 'Apply where '.$listing->company_name.' actually reads it',
            'detail' => $listing->company_url
                ? 'Go to the company\'s own site rather than the aggregator where you found this. The posting is real either way, but applications sent through the employer\'s own form reach a person sooner.'
                : 'Use the link on the posting. Keep a note of the date you applied — you will want it in two weeks.',
            'url' => $listing->company_url ?: $listing->source_url,
            'yours' => true,
        ];

        if ($listing->work_kind === WorkKind::Apprenticeship) {
            $steps[] = [
                'title' => 'Ask what the credential is and who recognises it',
                'detail' => 'An apprenticeship is worth what its certificate is worth. Ask which body registers it and whether it transfers if you move. A programme that cannot answer that is worth less than it sounds.',
                'url' => null,
                'yours' => true,
            ];
        }

        $steps[] = [
            'title' => 'Write the message yourself',
            'detail' => 'Three sentences: what you can do, when you can work, and one specific thing about why them. The coach will ask you questions about it. It will not write it, and an employer can tell.',
            'url' => null,
            'yours' => true,
        ];

        $steps[] = [
            'title' => 'Tell somebody where the interview is',
            'detail' => 'A parent, a counsellor, anyone. The address, the time, and the name of who you are meeting. This is not about being afraid — it is what adults do too, and it is the one part of applying for work nobody thinks to tell you.',
            'url' => null,
            'yours' => true,
        ];

        $steps[] = [
            'title' => 'Follow up once, after a week',
            'detail' => 'One short message. Following up once separates you from most applicants; following up four times does the opposite.',
            'url' => null,
            'yours' => true,
        ];

        return $steps;
    }

    /**
     * What to check before the first day, phrased as questions the student
     * asks rather than as rules we assert.
     *
     * These are the questions whose absence is the actual risk: hours, pay,
     * supervision and who to tell if something is wrong. A student who has
     * asked all four is much harder to exploit than one who has read a
     * paragraph about their rights.
     *
     * @return list<string>
     */
    public function questionsToAsk(User $student, JobListing $listing): array
    {
        $questions = [
            'What hours would I actually work, and who decides them week to week?',
            'What does it pay, and when does it pay — weekly, fortnightly, by cash or by transfer?',
            'Who would I report to, and will there be somebody there on the shifts I work?',
        ];

        if ($this->isMinor($student)) {
            $questions[] = 'I am under 18 — what paperwork do you need from my school, and are there hours I am not allowed to work?';
            $questions[] = 'Who do I talk to if something happens at work that I am not comfortable with?';
        }

        if ($listing->supervised === false) {
            $questions[] = 'This is unsupervised work. Who is the person I call if something goes wrong while I am on my own?';
        }

        return $questions;
    }

    /**
     * Unknown birthdate counts as a minor, matching {@see AccessPolicy}.
     */
    protected function isMinor(User $student): bool
    {
        return ($student->birthdate?->age ?? 0) < StudentJobs::ADULTHOOD;
    }
}
