<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');

        if ($organization instanceof Organization) {
            $orgId = $organization->id;
        } else {
            $orgId = $organization;
        }

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $isAdmin = $user->organizations()
            ->where('organizations.id', $orgId)
            ->wherePivot('role', 'admin')
            ->exists();

        if (! $isAdmin) {
            abort(403, 'You are not an administrator of this organization.');
        }

        return $next($request);
    }
}
