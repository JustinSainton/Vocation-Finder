<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgMemberController extends Controller
{
    public function index(Organization $organization): Response
    {
        $members = $organization->users()
            ->withCount(['assessments' => function ($q) use ($organization) {
                $q->where('organization_id', $organization->id);
            }])
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'assessments_count' => $user->assessments_count,
                'joined_at' => $user->pivot->created_at?->toDateString(),
            ]);

        $pendingInvitations = $organization->invitations()
            ->whereNull('accepted_at')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'created_at' => $inv->created_at->toDateString(),
            ]);

        return Inertia::render('Org/Members/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'memberLimit' => $organization->memberLimit(),
        ]);
    }

    public function invite(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:member,admin',
        ]);

        $currentCount = $organization->users()->count();
        $pendingCount = $organization->invitations()->whereNull('accepted_at')->count();

        if (($currentCount + $pendingCount) >= $organization->memberLimit()) {
            return redirect()->back()->withErrors([
                'email' => 'Member limit reached for this organization.',
            ]);
        }

        $organization->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => \Illuminate\Support\Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return redirect()->back();
    }

    public function remove(Organization $organization, User $user): RedirectResponse
    {
        // Prevent removing the last admin
        $pivot = $organization->users()->where('user_id', $user->id)->first()?->pivot;
        if ($pivot?->role === 'admin') {
            $adminCount = $organization->users()->wherePivot('role', 'admin')->count();
            if ($adminCount <= 1) {
                return redirect()->back()->with('error', 'Cannot remove the last administrator.');
            }
        }

        $organization->users()->detach($user->id);

        return redirect()->back();
    }

    public function show(Organization $organization, User $user): Response
    {
        $isMember = $organization->users()->where('users.id', $user->id)->exists();
        if (! $isMember) {
            abort(404);
        }

        $assessments = $user->assessments()
            ->where('organization_id', $organization->id)
            ->with('vocationalProfile:id,assessment_id,primary_domain,mode_of_work')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'mode' => $a->mode,
                'created_at' => $a->created_at->toDateString(),
                'completed_at' => $a->completed_at?->toDateString(),
                'primary_domain' => $a->vocationalProfile?->primary_domain,
                'mode_of_work' => $a->vocationalProfile?->mode_of_work,
            ]);

        return Inertia::render('Org/Members/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'assessments' => $assessments,
        ]);
    }
}
