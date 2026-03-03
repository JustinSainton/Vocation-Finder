<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $request->route('organization')
            ?? $request->input('organization_id');

        if (! $organizationId) {
            abort(400, 'Organization ID required.');
        }

        $org = Organization::findOrFail($organizationId);

        if ($org->subscription_status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Organization subscription required.',
                ], 403);
            }

            return redirect('/pricing');
        }

        return $next($request);
    }
}
