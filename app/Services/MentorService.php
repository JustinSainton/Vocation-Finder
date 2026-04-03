<?php

namespace App\Services;

use App\Models\MentorAssignment;
use App\Models\MentorNote;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MentorService
{
    /**
     * Assign a mentor to a student within an organization.
     */
    public function assign(Organization $organization, User $mentor, User $student): MentorAssignment
    {
        // Validate mentor is in the org with mentor or admin role
        $mentorPivot = $organization->users()
            ->where('user_id', $mentor->id)
            ->first()?->pivot;

        if (! $mentorPivot || ! in_array($mentorPivot->role, ['mentor', 'admin'])) {
            throw new \InvalidArgumentException('User must have mentor or admin role in this organization.');
        }

        // Validate student is in the org
        if (! $organization->users()->where('user_id', $student->id)->exists()) {
            throw new \InvalidArgumentException('Student must be a member of this organization.');
        }

        // Prevent self-assignment
        if ($mentor->id === $student->id) {
            throw new \InvalidArgumentException('A user cannot be assigned as their own mentor.');
        }

        // Complete any existing active assignment for this student in this org
        MentorAssignment::where('organization_id', $organization->id)
            ->where('student_id', $student->id)
            ->active()
            ->each(fn ($a) => $a->complete());

        return MentorAssignment::create([
            'organization_id' => $organization->id,
            'mentor_id' => $mentor->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    /**
     * Unassign a mentor from a student.
     */
    public function unassign(MentorAssignment $assignment): void
    {
        $assignment->complete();
    }

    /**
     * Remove a user from an organization with proper cascade.
     */
    public function removeFromOrganization(Organization $organization, User $user): void
    {
        DB::transaction(function () use ($organization, $user) {
            // Complete any mentor assignments where this user is mentor
            MentorAssignment::where('organization_id', $organization->id)
                ->where('mentor_id', $user->id)
                ->active()
                ->each(fn ($a) => $a->complete());

            // Complete any mentor assignments where this user is student
            MentorAssignment::where('organization_id', $organization->id)
                ->where('student_id', $user->id)
                ->active()
                ->each(fn ($a) => $a->complete());

            // Soft-delete mentor notes about this user in this org
            MentorNote::where('organization_id', $organization->id)
                ->where('student_id', $user->id)
                ->delete();

            // Nullify org association on assessments (privacy)
            $user->assessments()
                ->where('organization_id', $organization->id)
                ->update(['organization_id' => null]);

            // Remove from org
            $organization->users()->detach($user->id);
        });
    }
}
