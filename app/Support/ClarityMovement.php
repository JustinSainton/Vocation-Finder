<?php

namespace App\Support;

use App\Enums\ClarityMoment;
use App\Enums\ClarityShift;
use App\Models\Assessment;
use App\Models\ClarityCheck;
use Illuminate\Support\Collection;

/**
 * Whether anything moved between the two readings.
 *
 * Blueprint §14 names clarity measured before and after as one of the clearest
 * indicators of usefulness, and this is the whole of the computation: two
 * words, compared. No model, no scoring, nothing interpretive — the same shape
 * as {@see ReadinessCalculator}.
 *
 * **Nothing here is ever shown to the student.** A person told they became 40%
 * clearer has been graded on their own feelings by the thing that was supposed
 * to help them, and a person told they got *less* clear has been told they
 * failed an assessment they did not know they were taking. This exists so we
 * can tell whether the product works, and that is a question about us.
 */
class ClarityMovement
{
    public function for(Assessment $assessment): ClarityShift
    {
        $checks = $assessment->relationLoaded('clarityChecks')
            ? $assessment->clarityChecks
            : $assessment->clarityChecks()->get();

        $before = $checks->firstWhere('moment', ClarityMoment::Before);
        $after = $checks->firstWhere('moment', ClarityMoment::After);

        if (! $before instanceof ClarityCheck || ! $after instanceof ClarityCheck) {
            return ClarityShift::Unmeasured;
        }

        return match (true) {
            $after->standing->rank() > $before->standing->rank() => ClarityShift::Clearer,
            $after->standing->rank() < $before->standing->rank() => ClarityShift::LessClear,
            default => ClarityShift::Unchanged,
        };
    }

    /**
     * The read-out across a set of assessments.
     *
     * Counts, never rates. A pilot of twelve people produces percentages that
     * look like findings and are not, and the blueprint's own instruction is
     * to avoid overclaiming — a rule that has to bind us before it binds the
     * narrative. {@see ValidationReadout} is where a rate may be computed, and
     * only above a sample size that supports one.
     *
     * Unmeasured pairs are counted as unmeasured and never folded into
     * "unchanged": treating a missing reading as no-change is the most
     * flattering possible error in the one metric singled out as the measure
     * of usefulness.
     *
     * @param  Collection<int, Assessment>  $assessments
     * @return array<string, int>
     */
    public function across(Collection $assessments): array
    {
        $counts = array_fill_keys(ClarityShift::values(), 0);

        foreach ($assessments as $assessment) {
            $counts[$this->for($assessment)->value]++;
        }

        return $counts;
    }
}
