<?php

namespace App\Policies;

use App\Models\MentorAssignment;
use App\Models\MentorNote;
use App\Models\User;

class MentorNotePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Mentors see all notes they wrote; students see only shared notes about them.
     * Org admins see all notes in their org (handled by before()).
     */
    public function view(User $user, MentorNote $note): bool
    {
        // The mentor who wrote it
        if ($note->mentor_id === $user->id) {
            return true;
        }

        // The student — only if shared
        if ($note->student_id === $user->id && $note->isShared()) {
            return true;
        }

        // Org admin
        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $note->organization_id)
            ->exists();
    }

    /**
     * Only mentors assigned to the student can create notes.
     */
    public function create(User $user, string $organizationId, string $studentId): bool
    {
        return MentorAssignment::where('organization_id', $organizationId)
            ->where('mentor_id', $user->id)
            ->where('student_id', $studentId)
            ->active()
            ->exists();
    }

    /**
     * Only the note's author can update it.
     */
    public function update(User $user, MentorNote $note): bool
    {
        return $note->mentor_id === $user->id;
    }

    /**
     * Only the note's author or an org admin can delete it.
     */
    public function delete(User $user, MentorNote $note): bool
    {
        if ($note->mentor_id === $user->id) {
            return true;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $note->organization_id)
            ->exists();
    }
}
