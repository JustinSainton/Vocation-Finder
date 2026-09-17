<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ParentConsent;
use App\Notifications\ParentConsentNotification;
use App\Support\AccessPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A parent saying yes, once, on their own link.
 *
 * Consent is deliberately its own flow rather than a checkbox on checkout.
 * A parent who paid has not thereby consented, and letting a payment stand in
 * for permission is the wrong direction for a minor's account.
 */
class ParentConsentController extends Controller
{
    /**
     * The student nominating who to ask.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_email' => ['required', 'email', 'max:255'],
        ]);

        $user = $request->user();

        if (! AccessPolicy::requiresParentConsent($user)) {
            return back()->with('status', 'This account does not need a parent to sign off.');
        }

        $consent = $user->parentConsents()->firstOrCreate(
            ['parent_email' => $validated['parent_email'], 'status' => 'pending'],
            ['parent_name' => $validated['parent_name']],
        );

        /**
         * Sent on demand: a parent has no account here and should not need to
         * make one in order to say yes.
         */
        Notification::route('mail', $consent->parent_email)
            ->notify(new ParentConsentNotification($consent));

        return back()->with('status', "We have sent {$consent->parent_name} a link.");
    }

    /**
     * The parent's own page, reached by token rather than by logging in.
     *
     * Deliberately outside auth: requiring a parent to create an account to
     * say yes adds a step that loses students whose families are busy, which
     * is precisely the population this product is for.
     */
    public function show(string $token): Response
    {
        $consent = ParentConsent::where('token', $token)->firstOrFail();

        return Inertia::render('ParentConsent/Show', [
            'parentName' => $consent->parent_name,
            'studentName' => $consent->user->name,
            'granted' => $consent->isGranted(),
            'token' => $token,
        ]);
    }

    public function grant(Request $request, string $token): RedirectResponse
    {
        $consent = ParentConsent::where('token', $token)->firstOrFail();

        $consent->grant($request->ip());

        return back()->with('status', 'Thank you. Your student can get started.');
    }

    public function revoke(Request $request, string $token): RedirectResponse
    {
        $consent = ParentConsent::where('token', $token)->firstOrFail();

        $consent->revoke();

        return back()->with('status', 'Consent withdrawn. Nothing your student has written is deleted.');
    }
}
