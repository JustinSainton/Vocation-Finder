<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The plan's sections: a student's life in chunks, computed rather than
 * authored.
 *
 * Nobody types "Junior year, August to May" into a form. The school calendar
 * and the student's grade level already contain it, and a value a human has to
 * enter is a value that goes stale in September. The same rule that stops us
 * asking a model for something we can compute stops us asking a sixteen-year-
 * old.
 *
 * Sections stop at the end of high school. What comes after is one open
 * section with no end date, because the product's claim is that it removes
 * decision friction — and inventing four named years of a college the student
 * has not chosen would be foreclosing the decision instead.
 */
class PlanSections
{
    /**
     * The month a school year begins and the month it ends, inclusive. August
     * to May, with June and July as the summer between.
     */
    protected const YEAR_STARTS = 8;

    protected const YEAR_ENDS = 5;

    protected const FINAL_GRADE = 12;

    /**
     * @return array<int, string>
     */
    public static function gradeNames(): array
    {
        return [9 => 'Freshman year', 10 => 'Sophomore year', 11 => 'Junior year', 12 => 'Senior year'];
    }

    /**
     * @return list<array{key: string, label: string, starts_on: string, ends_on: string|null, is_now: bool}>
     */
    public function for(User $user, ?CarbonImmutable $asOf = null): array
    {
        $today = $asOf ?? CarbonImmutable::now();
        $grade = $user->grade_level;

        if ($grade === null || $grade > self::FINAL_GRADE) {
            return [$this->openEnded($today)];
        }

        $grade = max(9, $grade);
        $yearStart = $this->schoolYearStart($today);
        $sections = [];

        for ($offset = 0; $grade + $offset <= self::FINAL_GRADE; $offset++) {
            $currentGrade = $grade + $offset;
            $opens = $yearStart->addYears($offset);
            $closes = $opens->addYear()->month(self::YEAR_ENDS)->endOfMonth()->startOfDay();

            $sections[] = $this->section(
                'grade-'.$currentGrade,
                self::gradeNames()[$currentGrade],
                $opens,
                $closes,
                $today,
            );

            if ($currentGrade === self::FINAL_GRADE) {
                break;
            }

            $sections[] = $this->section(
                'summer-after-'.$currentGrade,
                'Summer after '.strtolower(self::gradeNames()[$currentGrade]),
                $closes->addDay(),
                $opens->addYear()->subDay(),
                $today,
            );
        }

        $sections[] = $this->openEnded(
            CarbonImmutable::parse(end($sections)['ends_on'])->addDay(),
            'After high school',
            $today,
        );

        return $sections;
    }

    /**
     * @return array{key: string, label: string, starts_on: string, ends_on: string|null, is_now: bool}
     */
    protected function openEnded(
        CarbonImmutable $opens,
        string $label = 'What comes next',
        ?CarbonImmutable $today = null,
    ): array {
        $today ??= $opens;

        return [
            'key' => 'open',
            'label' => $label,
            'starts_on' => $opens->toDateString(),
            'ends_on' => null,
            'is_now' => $today->greaterThanOrEqualTo($opens),
        ];
    }

    /**
     * @return array{key: string, label: string, starts_on: string, ends_on: string, is_now: bool}
     */
    protected function section(
        string $key,
        string $label,
        CarbonImmutable $opens,
        CarbonImmutable $closes,
        CarbonImmutable $today,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'starts_on' => $opens->toDateString(),
            'ends_on' => $closes->toDateString(),
            'is_now' => $today->betweenIncluded($opens, $closes),
        ];
    }

    /**
     * The first day of the school year the student is currently in. Before
     * August, that is last August — a junior in March is still a junior.
     */
    protected function schoolYearStart(CarbonImmutable $today): CarbonImmutable
    {
        $year = $today->month >= self::YEAR_STARTS ? $today->year : $today->year - 1;

        return CarbonImmutable::create($year, self::YEAR_STARTS, 1)->startOfDay();
    }
}
