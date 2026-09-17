<?php

namespace App\Support;

use App\Enums\FeedbackQuestion;
use App\Enums\ReviewDimension;
use App\Models\EvaluationFeedback;
use App\Models\PortraitReview;
use App\Models\User;
use App\Models\VocationalProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Roadmap 5.3 — which portrait a reviewer reads next.
 *
 * The blueprint asks for human review across nine dimensions. The dimensions
 * are the easy part; *which portraits get read* is where a review programme
 * quietly stops being a measurement.
 *
 * **A reviewer does not choose.** {@see self::next()} takes no portrait id and
 * there is no route that accepts one. A queue people pick from becomes a queue
 * of interesting cases, and "interesting" correlates with everything except
 * representativeness — the dull, fluent, slightly-wrong portrait that nobody
 * volunteers to read is the exact failure mode this product has.
 *
 * **The cases students objected to come first.** A student who said "this is
 * not me" has already done the hard part of the review; leaving that portrait
 * at the back of a chronological queue throws away the best-evidenced finding
 * we have.
 *
 * **A reviewer never sees a portrait twice**, and two reviewers reading the
 * same portrait is allowed and useful — disagreement between reviewers is a
 * finding about the dimension, not a scheduling error.
 */
class ReviewQueue
{
    /**
     * The next portrait for this reviewer, or null when they are through it.
     */
    public function next(User $reviewer): ?VocationalProfile
    {
        return $this->for($reviewer, limit: 1)->first();
    }

    /**
     * @return Collection<int, VocationalProfile>
     */
    public function for(User $reviewer, int $limit = 20): Collection
    {
        $seen = PortraitReview::query()
            ->where('reviewer_id', $reviewer->id)
            ->distinct()
            ->pluck('vocational_profile_id');

        $unread = fn (Builder $query) => $query->whereNotIn('id', $seen);

        $objected = VocationalProfile::query()
            ->tap($unread)
            ->whereIn('assessment_id', $this->assessmentsStudentsObjectedTo())
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($objected->count() >= $limit) {
            return $objected;
        }

        $rest = VocationalProfile::query()
            ->tap($unread)
            ->whereNotIn('id', $objected->pluck('id'))
            ->orderBy('created_at')
            ->limit($limit - $objected->count())
            ->get();

        return $objected->concat($rest);
    }

    /**
     * How much of the corpus has been read at all, and where it failed.
     *
     * Reported as counts per dimension rather than as a single quality figure.
     * Eight dimensions holding does not cancel one failing, which is why there
     * is no total here to average.
     *
     * @return array{portraits: int, reviewed: int, failures: array<string, int>}
     */
    public function coverage(): array
    {
        $failures = [];

        foreach (ReviewDimension::cases() as $dimension) {
            $failures[$dimension->value] = PortraitReview::query()
                ->where('dimension', $dimension)
                ->where('standing', 'fails')
                ->count();
        }

        return [
            'portraits' => VocationalProfile::query()->count(),
            'reviewed' => PortraitReview::query()->distinct()->count('vocational_profile_id'),
            'failures' => $failures,
        ];
    }

    /**
     * Assessments where the student themselves said the portrait was wrong or
     * left without knowing what to do.
     *
     * @return Collection<int, string>
     */
    protected function assessmentsStudentsObjectedTo(): Collection
    {
        return EvaluationFeedback::query()
            ->whereIn('question', [FeedbackQuestion::SoundsLikeMe, FeedbackQuestion::ClearNextStep])
            ->whereIn('standing', ['not_at_all', 'somewhat'])
            ->distinct()
            ->pluck('assessment_id');
    }
}
