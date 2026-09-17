<?php

namespace App\Support;

use App\Models\College;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The application, mapped out step by step — and never taken.
 *
 * The vision states the boundary in one line: *"The tool will not apply for
 * them — it gives them the precise links and guides them through it step by
 * step."* This class is that line in code. Every step is a thing the student
 * does, addressed to the student, and the only automation is knowing what the
 * steps are and when they are due.
 *
 * The reason is the product's founding one. A student who did not do their own
 * application arrives at a college they did not choose, and the tool that
 * filed it has removed the exact piece of agency the whole thing exists to
 * build. It is also the difference between a guide and a fraud: an application
 * is a signed attestation by the applicant.
 *
 * There is therefore deliberately no `submit()` here, no stored credentials,
 * no form-filling, and nothing that posts anywhere. The steps carry links.
 */
class CollegeApplication
{
    /**
     * Steps in the order they must actually happen, each with the real link.
     *
     * @return list<array{title: string, detail: string, url: ?string, due_on: ?string, yours: bool}>
     */
    public function steps(User $student, College $college, ?CarbonImmutable $asOf = null): array
    {
        $deadline = $this->deadline($college, $asOf);

        $steps = [
            [
                'title' => 'Open the application',
                'detail' => $college->application_system
                    ? $college->name.' takes applications through '.$college->application_system.'. Make the account yourself — it is yours, and you will need it again.'
                    : 'Applications go directly to '.$college->name.'. Make the account yourself — it is yours, and you will need it again.',
                'url' => $college->application_url,
                'due_on' => null,
                'yours' => true,
            ],
            [
                'title' => 'Ask for your transcript',
                'detail' => 'Your school sends this, not you. Ask the counselling office at least three weeks before the deadline — they are doing this for the whole grade at once.',
                'url' => null,
                'due_on' => $deadline?->subWeeks(3)->toDateString(),
                'yours' => true,
            ],
            [
                'title' => 'Ask two people for a recommendation',
                'detail' => 'Ask in person, and ask people who have seen you work rather than people with impressive titles. Give them a month.',
                'url' => null,
                'due_on' => $deadline?->subMonth()->toDateString(),
                'yours' => true,
            ],
            [
                'title' => 'Write the essay yourself',
                'detail' => 'This is the one part of the application that is only you. The coach will ask you questions about it and read what you wrote back to you. It will not write it, and a college can tell.',
                'url' => null,
                'due_on' => $deadline?->subWeeks(2)->toDateString(),
                'yours' => true,
            ],
        ];

        if ($college->application_fee > 0) {
            $steps[] = [
                'title' => 'Deal with the '.$this->money($college->application_fee).' fee',
                'detail' => $college->fee_waiver_available
                    ? 'A fee waiver is available and asking for one is routine — it is a checkbox, not a favour, and it does not reach the people reading your application.'
                    : 'This school does not waive the fee. Budget for it before you add more schools to the list.',
                'url' => null,
                'due_on' => $deadline?->toDateString(),
                'yours' => true,
            ];
        }

        $steps[] = [
            'title' => 'File the FAFSA',
            'detail' => 'Free, federal, and the thing that decides most of your aid. Every figure we show you about what college costs assumes this is filed.',
            'url' => 'https://studentaid.gov/h/apply-for-aid/fafsa',
            'due_on' => $deadline?->toDateString(),
            'yours' => true,
        ];

        $steps[] = [
            'title' => 'Submit it',
            'detail' => 'You press the button. We will not, and nobody else should either — an application is a statement you are signing.',
            'url' => $college->application_url,
            'due_on' => $deadline?->toDateString(),
            'yours' => true,
        ];

        return $steps;
    }

    /**
     * The next occurrence of the published deadline.
     *
     * Rolls to next year once this year's has passed, so a senior looking in
     * February sees the date they are actually working toward rather than one
     * that has already gone.
     */
    public function deadline(College $college, ?CarbonImmutable $asOf = null): ?CarbonImmutable
    {
        if ($college->deadline_month === null || $college->deadline_day === null) {
            return null;
        }

        $now = $asOf ?? CarbonImmutable::now();
        $deadline = $now->setDate($now->year, $college->deadline_month, $college->deadline_day)->startOfDay();

        return $deadline->lessThan($now->startOfDay()) ? $deadline->addYear() : $deadline;
    }

    protected function money(int $dollars): string
    {
        return '$'.number_format($dollars);
    }
}
