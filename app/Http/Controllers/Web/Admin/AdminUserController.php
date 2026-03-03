<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::withCount('assessments');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toDateTimeString(),
                'assessment_credits' => $user->assessment_credits,
            ],
            'assessments' => $user->assessments()
                ->latest()
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'mode' => $a->mode,
                    'status' => $a->status,
                    'created_at' => $a->created_at->toDateTimeString(),
                ]),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'role' => ['required', 'in:individual,admin,org_admin'],
        ]);

        $user->update($validated);

        return redirect("/admin/users/{$user->id}")->with('success', 'User updated.');
    }
}
