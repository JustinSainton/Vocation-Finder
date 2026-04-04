<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ResumeVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Resumes/Index', [
            'resumes' => $user->resumeVersions()
                ->with('jobListing:id,title,company_name')
                ->orderByDesc('created_at')
                ->paginate(20),
            'coverLetters' => $user->coverLetters()
                ->with('jobListing:id,title,company_name')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, ResumeVersion $resumeVersion): Response
    {
        if ($resumeVersion->user_id !== $request->user()->id) {
            abort(403);
        }

        $resumeVersion->load('jobListing:id,title,company_name');

        return Inertia::render('Resumes/Show', [
            'resume' => $resumeVersion,
        ]);
    }

    public function conversation(): Response
    {
        return Inertia::render('Resumes/Conversation');
    }
}
