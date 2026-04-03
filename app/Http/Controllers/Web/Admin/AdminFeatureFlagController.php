<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeatureFlagController extends Controller
{
    public function __construct(
        private FeatureFlagService $flags,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/FeatureFlags/Index', [
            'flags' => FeatureFlag::orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $this->flags->toggle($featureFlag->key, $validated['is_enabled']);

        return redirect('/admin/feature-flags')->with('success', "{$featureFlag->name} " . ($validated['is_enabled'] ? 'enabled' : 'disabled') . '.');
    }
}
