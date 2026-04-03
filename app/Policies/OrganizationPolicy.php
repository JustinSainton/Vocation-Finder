<?php

namespace App\Policies;

use App\Models\MentorAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OrganizationPolicy
{
    /**
     * Platform admins bypass all org-level checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Any org member can view their org.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Only org admins can manage settings, billing, and members.
     */
    public function manage(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->wherePivot('role', 'admin')
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Org admins and mentors can view students.
     * Mentors only see their assigned students (enforced at query level).
     */
    public function viewStudents(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->wherePivotIn('role', ['admin', 'mentor'])
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Only org admins can assign mentors.
     */
    public function assignMentor(User $user, Organization $organization): bool
    {
        return $this->manage($user, $organization);
    }

    /**
     * Only org admins can invite new members.
     */
    public function invite(User $user, Organization $organization): bool
    {
        return $this->manage($user, $organization);
    }

    /**
     * Org admins and mentors can view insights.
     */
    public function viewInsights(User $user, Organization $organization): bool
    {
        return $this->viewStudents($user, $organization);
    }
}
