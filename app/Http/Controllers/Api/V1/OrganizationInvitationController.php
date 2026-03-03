<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrganizationInvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'email' => ['required', 'email'],
            'role' => ['sometimes', 'in:member,admin'],
        ]);

        $org = $request->user()->organizations()->findOrFail($validated['organization_id']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => $validated['email'],
            'role' => $validated['role'] ?? 'member',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $validated['email'])
            ->notify(new OrganizationInvitationNotification($invitation));

        return response()->json([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::pending()
            ->where('token', $token)
            ->with('organization:id,name')
            ->firstOrFail();

        return response()->json([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'organization' => [
                'id' => $invitation->organization->id,
                'name' => $invitation->organization->name,
            ],
            'expires_at' => $invitation->expires_at,
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::pending()
            ->where('token', $token)
            ->firstOrFail();

        $user = $request->user();

        $invitation->organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role],
        ]);

        $invitation->update(['accepted_at' => now()]);

        return response()->json([
            'message' => 'Invitation accepted.',
            'organization_id' => $invitation->organization_id,
        ]);
    }
}
