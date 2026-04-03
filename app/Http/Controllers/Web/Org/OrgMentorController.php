<?php

namespace App\Http\Controllers\Web\Org;

use App\Http\Controllers\Controller;
use App\Models\MentorAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Services\MentorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgMentorController extends Controller
{
    public function __construct(
        private MentorService $mentorService,
    ) {}

    public function index(Organization $organization): Response
    {
        $mentors = $organization->users()
            ->wherePivotIn('role', ['mentor', 'admin'])
            ->withCount([
                'mentorAssignments as active_students' => fn ($q) => $q
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active'),
            ])
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'active_students' => $user->active_students,
            ]);

        $unassignedStudents = $organization->users()
            ->wherePivot('role', 'member')
            ->whereDoesntHave('studentAssignments', fn ($q) => $q
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
            )
            ->get(['users.id', 'users.name', 'users.email']);

        return Inertia::render('Org/Mentors/Index', [
            'mentors' => $mentors,
            'unassignedStudents' => $unassignedStudents,
        ]);
    }

    public function assign(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'mentor_id' => 'required|uuid|exists:users,id',
            'student_id' => 'required|uuid|exists:users,id',
        ]);

        $mentor = User::findOrFail($validated['mentor_id']);
        $student = User::findOrFail($validated['student_id']);

        $this->mentorService->assign($organization, $mentor, $student);

        return redirect()->back()->with('success', 'Mentor assigned successfully.');
    }

    public function unassign(Organization $organization, MentorAssignment $assignment): RedirectResponse
    {
        $this->mentorService->unassign($assignment);

        return redirect()->back()->with('success', 'Mentor unassigned.');
    }
}
