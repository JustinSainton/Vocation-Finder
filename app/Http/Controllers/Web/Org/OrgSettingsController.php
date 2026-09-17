<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings an organization owns rather than we do.
 *
 * Today that is one: whether staff may read a student's portrait. It is on by
 * default because a counsellor who cannot see the result cannot do the job the
 * school bought the tool for. It is a flag rather than a constant because the
 * organizations that must close it — a district with its own policy, a
 * programme working with a population where the guidance office is not a safe
 * reader — should not have to wait for a release.
 */
class OrgSettingsController extends Controller
{
    public function edit(Organization $organization): Response
    {
        return Inertia::render('Org/Settings', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'settings' => [
                Organization::SETTING_STAFF_MAY_READ_PORTRAITS => $organization->staffMayReadPortraits(),
            ],
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            Organization::SETTING_STAFF_MAY_READ_PORTRAITS => ['required', 'boolean'],
        ]);

        /**
         * Merged rather than assigned: `settings` is a shared bag and an
         * assignment here would silently drop every key a future setting adds.
         */
        $organization->update([
            'settings' => array_merge($organization->settings ?? [], [
                Organization::SETTING_STAFF_MAY_READ_PORTRAITS => $validated[Organization::SETTING_STAFF_MAY_READ_PORTRAITS],
            ]),
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
