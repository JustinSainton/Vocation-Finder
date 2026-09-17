<?php

namespace App\Support;

use App\Enums\AdmissionStanding;
use App\Enums\CollegeKind;
use App\Models\College;
use App\Models\User;
use App\Models\VocationalCategory;
use Illuminate\Support\Collection;

/**
 * The list of colleges, narrowed by what the student has already said.
 *
 * The vision asks for the tool to be "filtering colleges in real time based on
 * everything the student is saying". The literal reading — hand the transcript
 * to a model and ask it which colleges fit — is both the expensive way and the
 * unaccountable one, because nobody can afterwards say why a school appeared.
 *
 * What the student said has *already* been turned into verified signals and
 * scored categories by the nine-layer engine. So the filtering is a join: the
 * portrait names categories, the pivot maps categories to named programmes,
 * and a college surfaces because a programme it actually runs matches a
 * category the engine derived from something the student actually said. The
 * whole path from the saying to the surfacing is foreign keys a person can
 * read, and a student who asks "why is this here?" gets the programme name
 * rather than a shrug.
 *
 * The explicit filters below sit on top of that and are the student's own —
 * they are how a student overrules the portrait, which they must always be
 * able to do.
 */
class CollegeExplorer
{
    public function __construct(
        protected CollegeStanding $standing = new CollegeStanding,
        protected CollegeCost $cost = new CollegeCost,
    ) {}

    /**
     * @param  array{state?: ?string, kind?: ?string, max_cost?: ?int, standing?: ?string, category?: ?string}  $filters
     * @return list<array<string, mixed>>
     */
    public function results(User $student, array $filters = []): array
    {
        $categories = $this->categoriesFor($student, $filters['category'] ?? null);

        $query = College::query()->with('vocationalCategories');

        if ($categories->isNotEmpty()) {
            $query->whereHas('vocationalCategories', fn ($q) => $q->whereIn('vocational_categories.id', $categories->pluck('id')));
        }

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! empty($filters['kind']) && in_array($filters['kind'], CollegeKind::values(), true)) {
            $query->where('kind', $filters['kind']);
        }

        $colleges = $query->orderBy('name')->get();

        $rows = $colleges->map(fn (College $college) => $this->row($student, $college, $categories));

        /*
         | Cost and standing are filtered in PHP rather than SQL because both
         | are computed against this student — the net price depends on their
         | income bracket and the standing on their GPA, and neither is a
         | column anybody could index.
         */
        if (! empty($filters['max_cost'])) {
            $rows = $rows->filter(fn (array $row) => ($row['cost']['estimated'] ?? $row['cost']['published']) <= (int) $filters['max_cost']);
        }

        if (! empty($filters['standing']) && in_array($filters['standing'], AdmissionStanding::values(), true)) {
            $rows = $rows->filter(fn (array $row) => $row['standing']['value'] === $filters['standing']);
        }

        return $rows->values()->all();
    }

    /**
     * @param  Collection<int, VocationalCategory>  $categories
     * @return array<string, mixed>
     */
    protected function row(User $student, College $college, Collection $categories): array
    {
        $standing = $this->standing->for($student, $college);

        return [
            'id' => $college->id,
            'slug' => $college->slug,
            'name' => $college->name,
            'where' => $college->location(),
            'kind' => $college->kind->label(),
            'control' => $college->control->label(),
            'standing' => [
                'value' => $standing->value,
                'label' => $standing->label(),
                'description' => $standing->description(),
            ],
            'cost' => $this->cost->for($student, $college),
            /*
             | Why this school is in front of you, in the college's own words
             | for the programme rather than in our taxonomy's words.
             */
            'programs' => $college->vocationalCategories
                ->filter(fn (VocationalCategory $category) => $categories->isEmpty() || $categories->contains('id', $category->id))
                ->map(fn (VocationalCategory $category) => $category->pivot->program_name)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * The categories to narrow by: the student's own portrait, unless they
     * have asked for a specific one.
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
}
