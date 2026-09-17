<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\CohortView;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cohort, as a list of people who need a person this week.
 *
 * Open to mentors as well as admins, for the same reason the portrait is: a
 * counsellor who cannot see who is stuck cannot do the job the school bought
 * the tool for. What it does *not* open is the coaching conversation or the
 * brain — {@see CohortView} is the only thing that decides what crosses, and
 * this controller assembles nothing of its own.
 */
class OrgCohortController extends Controller
{
    public function index(Organization $organization, CohortView $cohort): Response
    {
        return Inertia::render('Org/Cohort', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'cohort' => $cohort->for($organization),
        ]);
    }
}
