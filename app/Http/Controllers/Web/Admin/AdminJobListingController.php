<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminJobListingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JobListing::with('vocationalCategories');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        if ($status = $request->query('classification_status')) {
            $query->where('classification_status', $status);
        }

        $jobs = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $stats = [
            'total' => JobListing::count(),
            'active' => JobListing::active()->count(),
            'classified' => JobListing::classified()->count(),
            'pending' => JobListing::pendingClassification()->count(),
            'sources' => JobListing::selectRaw('source, count(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
        ];

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => $jobs,
            'stats' => $stats,
            'filters' => $request->only(['search', 'source', 'classification_status']),
        ]);
    }
}
