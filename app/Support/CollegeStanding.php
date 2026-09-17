<?php

namespace App\Support;

use App\Enums\AdmissionStanding;
use App\Models\College;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Where a student stands against a college's published range — in words.
 *
 * **No model is involved and none ever should be.** A GPA compared against a
 * published middle-half range is arithmetic, and the governing guardrail is
 * that we never ask a model for a value we can compute. Asking a language
 * model whether a student will get into a school produces a confident,
 * ungrounded, unfalsifiable sentence about a seventeen-year-old's future,
 * which is close to the worst thing this product could emit.
 *
 * Money is deliberately absent. The vision names "GPA, finances, and more" in
 * one breath, and the temptation is to fold them into a single figure. This
 * class refuses: finances decide what a place *costs* ({@see CollegeCost}),
 * never whether somebody is good enough to be admitted. A student who learns
 * from our interface that being poor lowers their chances has been taught
 * something both false and corrosive.
 */
class CollegeStanding
{
    /**
     * A GPA below the 25th percentile is a reach; inside the middle half is in
     * range; at or above the 75th is likely. The boundaries are the published
     * ones rather than any judgement of ours, which is what makes the answer
     * checkable against the college's own common data set.
     */
    public function for(User $student, College $college): AdmissionStanding
    {
        if ($student->gpa === null || ! $college->publishesAdmissionRange()) {
            return AdmissionStanding::Unknown;
        }

        return match (true) {
            $student->gpa < $college->gpa_25th => AdmissionStanding::Reach,
            $student->gpa >= $college->gpa_75th => AdmissionStanding::Likely,
            default => AdmissionStanding::Possible,
        };
    }

    /**
     * The one honest thing that can be said about a whole list.
     *
     * Not a score for the list — a statement of what is missing from it. A
     * list of eight reaches and a list of eight likelies are both badly built,
     * and that is the rare claim about college admissions that can be made
     * from public data without pretending to predict anything.
     *
     * @param  Collection<int, College>  $list
     * @return list<string>
     */
    public function balanceAdvice(User $student, Collection $list): array
    {
        if ($list->isEmpty()) {
            return ['Nothing is on your list yet. Put one school on it — even one you are not sure about.'];
        }

        $present = $list->map(fn (College $college) => $this->for($student, $college))->unique();

        $missing = collect([AdmissionStanding::Reach, AdmissionStanding::Possible, AdmissionStanding::Likely])
            ->reject(fn (AdmissionStanding $standing) => $present->contains($standing));

        /*
         | When no school on the list publishes a range there is nothing to
         | balance, and saying "you are missing a reach" would be an artefact
         | of missing data rather than a fact about the list.
         */
        if ($present->count() === 1 && $present->first() === AdmissionStanding::Unknown) {
            return [AdmissionStanding::Unknown->listAdvice()];
        }

        return $missing->map(fn (AdmissionStanding $standing) => $standing->listAdvice())->values()->all();
    }
}
