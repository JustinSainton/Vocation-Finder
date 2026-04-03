<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureFlag
{
    public function __construct(
        private FeatureFlagService $flags,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->flags->isEnabled($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
