<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrganizationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => Organization::withCount(['users', 'assessments'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Organization $organization): Response
    {
        return Inertia::render('Admin/Organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'type' => $organization->type,
                'subscription_status' => $organization->subscription_status,
                'created_at' => $organization->created_at->toDateTimeString(),
            ],
            'members' => $organization->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ]),
            'assessment_stats' => [
                'total' => $organization->assessments()->count(),
                'completed' => $organization->assessments()->where('status', 'completed')->count(),
                'this_period' => $organization->assessmentsUsedThisPeriod(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Organizations/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
        ]);

        $org = Organization::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect("/admin/organizations/{$org->id}")->with('success', 'Organization created.');
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('Admin/Organizations/Form', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
        ]);

        $organization->update($validated);

        return redirect("/admin/organizations/{$organization->id}")->with('success', 'Organization updated.');
    }
}
