<?php

namespace App\Support;

use App\Enums\VettingStatus;
use App\Enums\WorkKind;
use App\Models\JobListing;
use App\Models\User;
use App\Models\VocationalCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What work a particular student is allowed to be shown.
 *
 * This is the only place that decides, for the same reason
 * {@see ParentVisibility} is the only place that decides what a parent sees. A
 * second query that assembles a job list is a second place that can forget the
 * age check, and the consequence of forgetting is a sixteen-year-old sent to
 * an employer who should never have been put in front of them.
 *
 * Three gates, all failing closed:
 *
 * 1. **Vetted.** Only {@see VettingStatus::Passed} is visible. Pending is the
 *    default and pending means absent — not "shown with a caution".
 * 2. **Old enough.** A listing whose stated minimum age is above the student's
 *    is filtered out, and so is a listing that states no age at all when the
 *    student is a minor. An unstated age is not permission.
 * 3. **Age known.** A student whose birthdate we do not have is treated as the
 *    youngest they could be, exactly as {@see AccessPolicy} treats an unknown
 *    birthdate as a minor. The costs of guessing are not symmetric.
 */
class StudentJobs
{
    /**
     * The age below which we require a listing to say what it requires.
     *
     * Eighteen rather than sixteen on purpose: federal child-labour rules turn
     * on being under eighteen, and a posting that never mentions age has not
     * been written with a minor in mind whatever the minor's exact age is.
     */
    public const ADULTHOOD = 18;

    /**
     * @param  array{kind?: ?string, remote?: ?bool, category?: ?string, search?: ?string}  $filters
     * @return Collection<int, JobListing>
     */
    public function visibleTo(User $student, array $filters = []): Collection
    {
        $query = JobListing::query()
            ->with('vocationalCategories')
            ->where('vetting_status', VettingStatus::Passed)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        $this->constrainByAge($query, $student);

        if (! empty($filters['kind']) && in_array($filters['kind'], WorkKind::values(), true)) {
            $query->where('work_kind', $filters['kind']);
        }

        if (! empty($filters['remote'])) {
            $query->where('is_remote', true);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%"));
        }

        $categories = $this->categoriesFor($student, $filters['category'] ?? null);

        if ($categories->isNotEmpty()) {
            $query->whereHas('vocationalCategories', fn (Builder $q) => $q->whereIn('vocational_categories.id', $categories->pluck('id')));
        }

        return $query->orderByDesc('posted_at')->get();
    }

    /**
     * The age gate, written once.
     *
     * For an adult this is a no-op — an adult may look at anything that passed
     * vetting. For anyone else, a listing must both state a minimum age and
     * state one this student meets. The `whereNull` case is deliberately
     * *excluded* rather than included: silence about age is the most common
     * shape of a posting that never considered a minor at all.
     */
    protected function constrainByAge(Builder $query, User $student): void
    {
        $age = $student->birthdate?->age;

        if ($age !== null && $age >= self::ADULTHOOD) {
            return;
        }

        /*
         | Belt and braces: SQL already excludes a null `minimum_age` from the
         | comparison below, since `null <= 16` is null rather than true. The
         | explicit check stays because the protection is then visible rather
         | than being an emergent property of three-valued logic that the next
         | person refactors away by adding an `orWhereNull`.
         */
        $query->whereNotNull('minimum_age');

        /*
         | An unknown birthdate is treated as the youngest a high schooler can
         | plausibly be rather than as an adult. Being wrong in this direction
         | hides some work from somebody who could have done it; being wrong in
         | the other direction sends a fourteen-year-old to a night shift.
         */
        $query->where('minimum_age', '<=', $age ?? 14);
    }

    /**
     * Why a listing is in front of this student: the same
     * portrait → category → listing join the college explorer uses, so the
     * reason is a foreign key rather than a judgement.
     *
     * @return Collection<int, VocationalCategory>
     */
    public function categoriesFor(User $student, ?string $categorySlug = null): Collection
    {
        if ($categorySlug) {
            return VocationalCategory::query()->where('slug', $categorySlug)->get();
        }

        $profile = $student->assessments()
            ->where('status', 'completed')
            ->latest()
            ->first()?->vocationalProfile;

        $names = collect($profile?->category_scores ?? [])
            ->sortByDesc(fn (array $score) => $score['score'] ?? 0)
            ->take(3)
            ->pluck('category')
            ->filter()
            ->values();

        return $names->isEmpty()
            ? collect()
            : VocationalCategory::query()->whereIn('name', $names)->get();
    }

    /**
     * Whether this student may open this one listing.
     *
     * The list query and the detail page must agree, and the only way to
     * guarantee that is for the detail page to ask the same object rather than
     * to re-implement the rule with an `if`.
     */
    public function mayOpen(User $student, JobListing $listing): bool
    {
        return $this->visibleTo($student)->contains('id', $listing->id);
    }
}
