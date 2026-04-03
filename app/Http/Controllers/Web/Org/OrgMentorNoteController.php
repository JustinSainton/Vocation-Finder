<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\MentorNote;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrgMentorNoteController extends Controller
{
    public function store(Request $request, Organization $organization, User $member): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'visibility' => 'sometimes|in:mentor_only,shared',
        ]);

        MentorNote::create([
            'organization_id' => $organization->id,
            'mentor_id' => $request->user()->id,
            'student_id' => $member->id,
            'content' => $validated['content'],
            'visibility' => $validated['visibility'] ?? 'mentor_only',
        ]);

        return redirect()->back()->with('success', 'Note added.');
    }

    public function update(Request $request, Organization $organization, MentorNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $validated = $request->validate([
            'content' => 'sometimes|string|max:5000',
            'visibility' => 'sometimes|in:mentor_only,shared',
        ]);

        $note->update($validated);

        return redirect()->back()->with('success', 'Note updated.');
    }

    public function destroy(Organization $organization, MentorNote $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return redirect()->back()->with('success', 'Note deleted.');
    }
}
