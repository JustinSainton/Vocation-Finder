<?php

namespace App\Support;

use App\Enums\CampusResourceKind;
use App\Models\College;
use App\Models\CollegeResource;
use App\Models\Enrollment;
use App\Models\User;

/**
 * What is on a campus, and the sentence to say when you get there.
 *
 * The vision's in-college layer is about not dropping out, and the research on
 * why first-generation students leave is not that the help was missing. It is
 * that they did not know it was for them. So every entry here carries three
 * things: what the place is, why using it is ordinary rather than an
 * admission of failure, and the opening line — because "I do not know what to
 * say when I walk in" is the actual obstacle, and it is one a tool can remove
 * without doing the student's work for them.
 *
 * The baseline comes from {@see CampusResourceKind}, which is true of every US
 * college. Institution-specific links are an upgrade layered on top when
 * somebody has imported them, never invented. Same refusal as the college net
 * prices in 4.2: we do not type facts about real organisations from memory.
 */
class CampusGuide
{
    /**
     * @return list<array{kind: string, label: string, description: string, opener: string, links: list<array{name: string, url: string}>}>
     */
    public function resources(?College $college = null): array
    {
        $links = $college
            ? $college->resources()->get()->groupBy(fn (CollegeResource $resource) => $resource->kind->value)
            : collect();

        return collect(CampusResourceKind::cases())
            ->map(fn (CampusResourceKind $kind) => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'description' => $kind->description(),
                'opener' => $kind->opener(),
                'links' => $links->get($kind->value, collect())
                    ->map(fn (CollegeResource $resource) => [
                        'name' => $resource->name,
                        'url' => $resource->url,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The student's current enrollment, if they have told us about one.
     *
     * Enrollment is asked for rather than inferred from age or grade level.
     * A guess that says yes pushes syllabus prompts at a high school senior;
     * a guess that says no withholds the whole layer from somebody who has
     * started. Neither is recoverable by the student, and the question takes
     * ten seconds.
     */
    public function currentEnrollment(User $student): ?Enrollment
    {
        return $student->enrollments()
            ->whereNull('ended_at')
            ->latest('started_on')
            ->first();
    }
}
