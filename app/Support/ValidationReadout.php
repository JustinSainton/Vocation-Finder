<?php

namespace App\Support;

use App\Enums\ActionStatus;
use App\Enums\EvaluationOutcome;
use App\Enums\FeedbackQuestion;
use App\Models\Action;
use App\Models\Assessment;
use App\Models\EvaluationFeedback;
use App\Models\EvaluationLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Roadmap 5.2 — whether the thing works, read honestly.
 *
 * Blueprint §14 names the measures: completion, perceived accuracy,
 * usefulness, next-step action rate, paid conversion, disagreement rate,
 * confusion rate. All of them already exist as records — clarity readings,
 * {@see EvaluationFeedback}, {@see Action}, {@see EvaluationLog}. This class
 * only counts them, and the interesting decisions are about how it refuses to.
 *
 * **A rate needs a sample.** Below {@see self::MINIMUM_SAMPLE} responses this
 * returns counts and a null rate with the reason attached. Eleven students and
 * eight yeses is "73% perceived accuracy" in a deck and nothing at all in
 * reality, and the pilot is deliberately 50–100 people — which means the
 * window where this product is most likely to overclaim about itself is the
 * window it is first shown to anybody. The blueprint's instruction to avoid
 * overclaiming has to bind the company before it binds the narrative.
 *
 * **Disagreement is a measure, not a defect.** A student saying "this is not
 * me" is the single most useful thing in here, so dissent is counted in its
 * own right rather than as the leftover of satisfaction. An average would bury
 * it; that is what averages are for.
 *
 * **We do not infer who anybody is.** The blueprint asks for bias testing
 * across culture, gender, socioeconomic background, neurodiversity and
 * educational access. We hold income band, grade level and school membership
 * because students told us, and we can segment on those. We do not hold the
 * others and we will not guess at them from names, language or writing style:
 * inferring a protected characteristic in order to prove we are fair to it
 * creates exactly the record we refused to collect, and a wrong guess assigns
 * a student to a group they are not in and then reports about them under it.
 * {@see self::SEGMENTS} is the allowlist and it is enforced, not advisory.
 */
class ValidationReadout
{
    /**
     * Below this, a proportion is an anecdote with a percent sign.
     */
    public const MINIMUM_SAMPLE = 30;

    /**
     * The only things we may split the numbers by: the ones students told us
     * directly. See the class docblock for why there is nothing else here.
     */
    public const SEGMENTS = ['household_income_band', 'grade_level', 'organization'];

    /**
     * @return array{sample: array<string, int>, measures: list<array<string, mixed>>}
     */
    public function for(?CarbonInterface $since = null): array
    {
        $assessments = $this->assessments($since)->get();
        $completed = $assessments->where('status', 'completed');

        return [
            'sample' => [
                'assessments' => $assessments->count(),
                'completed' => $completed->count(),
                'minimum_for_a_rate' => self::MINIMUM_SAMPLE,
            ],
            'measures' => [
                $this->measure(
                    'completion',
                    'Finished the assessment they started',
                    $completed->count(),
                    $assessments->count(),
                ),
                $this->clarity($completed),
                $this->perception(FeedbackQuestion::SoundsLikeMe, 'Said the portrait sounds like them', $since),
                $this->perception(FeedbackQuestion::Useful, 'Said any of it was useful', $since),
                $this->perception(FeedbackQuestion::ClearNextStep, 'Said they know what to do next', $since),
                $this->dissent(FeedbackQuestion::SoundsLikeMe, 'Disagreed with the portrait', $since),
                $this->dissent(FeedbackQuestion::ClearNextStep, 'Left without knowing what to do next', $since),
                $this->nextStepTaken($since),
                $this->paidConversion($since),
                $this->engineFailures($since),
            ],
        ];
    }

    /**
     * The same read-out for one slice of the students.
     *
     * @throws InvalidArgumentException when asked to segment on something we
     *                                  would have to guess at
     */
    public function segmentKeys(string $attribute): array
    {
        if (! in_array($attribute, self::SEGMENTS, true)) {
            throw new InvalidArgumentException(
                "Refusing to segment validation results by [{$attribute}]. We report only on what students told us "
                .'about themselves; inferring a characteristic in order to check we are fair to it creates the '
                .'record we declined to collect.'
            );
        }

        if ($attribute === 'organization') {
            return User::query()->has('organizations')->pluck('id')->all();
        }

        return User::query()
            ->whereNotNull($attribute)
            ->distinct()
            ->pluck($attribute)
            ->map(fn ($value) => is_object($value) ? $value->value : (string) $value)
            ->all();
    }

    /**
     * @param  Collection<int, Assessment>  $completed
     * @return array<string, mixed>
     */
    protected function clarity(Collection $completed): array
    {
        $counts = (new ClarityMovement)->across($completed);
        $measured = $counts['clearer'] + $counts['unchanged'] + $counts['less_clear'];

        return array_merge(
            $this->measure('clarity_improved', 'Left clearer than they arrived', $counts['clearer'], $measured),
            [
                'counts' => $counts,
                /*
                 | Surfaced beside the rate on purpose. The denominator here is
                 | pairs we actually hold, and a reader who cannot see how many
                 | were never measured cannot tell a working product from a
                 | question most people skipped.
                 */
                'unmeasured' => $counts['unmeasured'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function perception(FeedbackQuestion $question, string $label, ?CarbonInterface $since): array
    {
        $answers = $this->feedback($question, $since);

        return $this->measure(
            $question->value,
            $label,
            $answers->reject(fn (EvaluationFeedback $row) => $row->standing->isDissent())->count(),
            $answers->count(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function dissent(FeedbackQuestion $question, string $label, ?CarbonInterface $since): array
    {
        $answers = $this->feedback($question, $since);

        return array_merge(
            $this->measure(
                $question->value.'_dissent',
                $label,
                $answers->filter(fn (EvaluationFeedback $row) => $row->standing->isDissent())->count(),
                $answers->count(),
            ),
            ['read_these' => true],
        );
    }

    /**
     * Did the one next step actually happen.
     *
     * The denominator is actions assigned, not students: a student who was
     * never given a step cannot have failed to take one, and counting them
     * would turn a gap in the product into a verdict on them.
     *
     * @return array<string, mixed>
     */
    protected function nextStepTaken(?CarbonInterface $since): array
    {
        $assigned = Action::query()->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since));

        return $this->measure(
            'next_step_taken',
            'Took the next step they were given',
            (clone $assigned)->where('status', ActionStatus::Completed)->count(),
            $assigned->count(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function paidConversion(?CarbonInterface $since): array
    {
        /*
         | "Had a portrait" is the denominator, not "signed up": conversion
         | measured against everyone who ever made an account measures the
         | marketing site, not whether the result was worth paying for. A
         | portrait hangs off the assessment rather than the user, so the
         | relationship is walked rather than denormalised.
         */
        $students = User::query()
            ->whereHas('assessments', fn (Builder $query) => $query->whereHas('vocationalProfile'))
            ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->get();

        return $this->measure(
            'paid_conversion',
            'Went on to pay',
            $students->filter(fn (User $student) => $student->subscribed())->count(),
            $students->count(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineFailures(?CarbonInterface $since): array
    {
        $runs = EvaluationLog::query()->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since));

        return array_merge(
            $this->measure(
                'engine_failed',
                'Engine runs that did not finish',
                (clone $runs)->where('outcome', EvaluationOutcome::Failed)->count(),
                $runs->count(),
            ),
            ['read_these' => true],
        );
    }

    /**
     * One measure, with a rate only when the sample can carry one.
     *
     * @return array<string, mixed>
     */
    protected function measure(string $key, string $label, int $count, int $of): array
    {
        $reportable = $of >= self::MINIMUM_SAMPLE;

        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'of' => $of,
            'rate' => $reportable && $of > 0 ? round($count / $of, 3) : null,
            'why_no_rate' => $reportable
                ? null
                : "Too few to report a rate: {$of} of the ".self::MINIMUM_SAMPLE.' needed.',
        ];
    }

    /**
     * @return Collection<int, EvaluationFeedback>
     */
    protected function feedback(FeedbackQuestion $question, ?CarbonInterface $since): Collection
    {
        return EvaluationFeedback::query()
            ->where('question', $question)
            ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->get();
    }

    /**
     * @return Builder<Assessment>
     */
    protected function assessments(?CarbonInterface $since): Builder
    {
        return Assessment::query()
            ->with('clarityChecks')
            ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since));
    }
}
