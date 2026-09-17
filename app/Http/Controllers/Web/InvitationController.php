<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\CohortInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * How a student invited by their school actually gets in.
 *
 * Deliberately outside `auth`: the invitation is how somebody who has no
 * account arrives, so a login wall here would mean the link only works for
 * people who did not need it. A signed-out visitor is shown the same page and
 * sent to registration, and the token rides in the session so the form does
 * not have to carry a field that means nothing to the person filling it in.
 *
 * Accepting lands the student on `/next` rather than a dashboard. The point of
 * an invitation entry path is that it drops somebody into the sequence, not on
 * a home screen where they have to work out what this is.
 */
class InvitationController extends Controller
{
    /**
     * The session key the token rides in between the landing page and the
     * finished registration. It is scoped to one browser session on purpose —
     * an invitation stored on the user would outlive the moment it is for.
     */
    public const SESSION_KEY = 'cohort_invitation_token';

    public function show(Request $request, string $token, CohortInvitation $invitations): Response|RedirectResponse
    {
        $invitation = $invitations->find($token);

        if (! $invitation) {
            abort(404);
        }

        $request->session()->put(self::SESSION_KEY, $token);

        return Inertia::render('Invitations/Show', [
            'token' => $token,
            'organization_name' => $invitation->organization->name,
            'invited_email' => $invitation->email,
            'role' => $invitation->role,
            'blocked_reason' => $invitations->blockedReason($invitation),
            /*
             * The signed-in account is named rather than assumed. A student on
             * a shared library machine clicking their friend's link should be
             * able to see, before they press anything, whose account is about
             * to join the school.
             */
            'signed_in_as' => $request->user()?->email,
        ]);
    }

    public function accept(Request $request, string $token, CohortInvitation $invitations): RedirectResponse
    {
        $invitation = $invitations->find($token);

        if (! $invitation) {
            abort(404);
        }

        if (! $request->user()) {
            $request->session()->put(self::SESSION_KEY, $token);

            return redirect('/register');
        }

        try {
            $invitations->accept($invitation, $request->user());
        } catch (RuntimeException $exception) {
            return redirect("/invitations/{$token}")->with('status', $exception->getMessage());
        }

        $request->session()->forget(self::SESSION_KEY);

        /*
         * Always `/next`, including for a student whose sequence is already
         * over. That page answers "what now" for every step there is, and a
         * freshman who lands on it is told the portrait is theirs to keep
         * rather than being dropped on a dashboard to work it out.
         */
        return redirect('/next');
    }
}
