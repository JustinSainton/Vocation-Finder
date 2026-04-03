<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameterized middleware to check the user's role within an organization.
 *
 * Usage in routes:
 *   ->middleware('org.role:admin')           // only org admins
 *   ->middleware('org.role:admin,mentor')    // admins and mentors
 *   ->middleware('org.role:admin,mentor,member') // any org member
 *
 * Also shares the current org context to Inertia for persistent layouts.
 */
class EnsureOrgRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        // Platform admins bypass org role checks
        if ($user->role === 'admin') {
            $this->shareOrgContext($request);

            return $next($request);
        }

        $organization = $request->route('organization');

        if (! $organization) {
            abort(404);
        }

        $pivot = $organization->users()
            ->where('user_id', $user->id)
            ->first()?->pivot;

        if (! $pivot || ! in_array($pivot->role, $allowedRoles, true)) {
            abort(403, 'You do not have the required role in this organization.');
        }

        // Store the resolved role on the request for controllers to use
        $request->attributes->set('orgRole', $pivot->role);

        $this->shareOrgContext($request);

        return $next($request);
    }

    private function shareOrgContext(Request $request): void
    {
        $organization = $request->route('organization');

        if ($organization) {
            Inertia::share('currentOrg', [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ]);
            Inertia::share('orgRole', $request->attributes->get('orgRole', 'admin'));
        }
    }
}
