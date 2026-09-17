<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\GuestUpgradeService;
use App\Support\CohortInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, GuestUpgradeService $upgradeService): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $upgradeService->upgrade($user, $request->guest_token);

        Auth::login($user);

        if ($this->joinInvitedCohort($request, $user)) {
            return redirect('/next');
        }

        return redirect('/dashboard');
    }

    /**
     * Finish a school invitation the visitor started before they had an
     * account.
     *
     * The token rides in the session rather than in a form field, because a
     * hidden input that a student can see in the page source is a hidden input
     * a student can change, and the invitation is the permission.
     *
     * A stale or spent token is not an error here. The account has just been
     * created and the person is signed in; refusing the registration over an
     * expired link would throw away the part that worked.
     */
    protected function joinInvitedCohort(RegisterRequest $request, User $user): bool
    {
        $token = $request->session()->pull(InvitationController::SESSION_KEY);

        if (! $token) {
            return false;
        }

        $invitations = app(CohortInvitation::class);
        $invitation = $invitations->find($token);

        if (! $invitation || $invitations->blockedReason($invitation)) {
            return false;
        }

        $invitations->accept($invitation, $user);

        return true;
    }
}
