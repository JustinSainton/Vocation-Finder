<?php

namespace App\Support;

use App\Enums\StudentPlace;
use App\Models\User;
use App\Services\FeatureFlagService;

/**
 * Which of the five places this student can actually walk into.
 *
 * Shared on every response rather than passed by each controller, because
 * "first-class navigation" is precisely the claim that the places are not a
 * property of the page you happen to be on. A page that had to opt in could
 * forget to.
 */
class StudentNavigation
{
    /**
     * @return list<array{key: string, label: string, blurb: string, href: string}>
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $flags = app(FeatureFlagService::class);

        return array_values(array_map(
            fn (StudentPlace $place) => [
                'key' => $place->value,
                'label' => $place->label(),
                'blurb' => $place->blurb(),
                'href' => $place->path(),
            ],
            array_filter(
                StudentPlace::open(),
                fn (StudentPlace $place) => $place->gate() === null || $flags->isEnabled($place->gate()),
            ),
        ));
    }
}
