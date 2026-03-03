<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAssessmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow unauthenticated (guest) users their first free assessment
        if (! $user) {
            return $next($request);
        }

        // Allow if the user has never completed an assessment (free first assessment)
        if ($user->assessments()->where('status', 'completed')->doesntExist()) {
            return $next($request);
        }

        if ($user->hasAssessmentAccess()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Assessment access required. Please upgrade your plan.',
            ], 403);
        }

        return redirect('/pricing');
    }
}
