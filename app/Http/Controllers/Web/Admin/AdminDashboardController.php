<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'total_assessments' => Assessment::count(),
                'completed_assessments' => Assessment::where('status', 'completed')->count(),
                'active_organizations' => Organization::where('subscription_status', 'active')->count(),
            ],
            'recent_assessments' => Assessment::with('user:id,name,email')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Assessment $a) => [
                    'id' => $a->id,
                    'user_name' => $a->user?->name ?? 'Guest',
                    'mode' => $a->mode,
                    'status' => $a->status,
                    'created_at' => $a->created_at->toDateTimeString(),
                ]),
        ]);
    }
}
