<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\MentorAssignment;
use App\Models\User;

class AssessmentPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * User can view their own assessment, or a mentor can view their assigned student's assessment.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        // Owner
        if ($assessment->user_id === $user->id) {
            return true;
        }

        // Org admin for the assessment's org
        if ($assessment->organization_id) {
            $isOrgAdmin = $user->organizations()
                ->wherePivot('role', 'admin')
                ->where('organizations.id', $assessment->organization_id)
                ->exists();

            if ($isOrgAdmin) {
                return true;
            }

            // Mentor assigned to this student within the same org
            $isMentor = MentorAssignment::where('organization_id', $assessment->organization_id)
                ->where('mentor_id', $user->id)
                ->where('student_id', $assessment->user_id)
                ->active()
                ->exists();

            if ($isMentor) {
                return true;
            }
        }

        return false;
    }
}
