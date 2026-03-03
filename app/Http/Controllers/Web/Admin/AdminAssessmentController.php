<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Assessment::with('user:id,name,email');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Admin/Assessments/Index', [
            'assessments' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function show(Assessment $assessment): Response
    {
        $assessment->load(['user:id,name,email', 'answers.question.category', 'vocationalProfile']);

        return Inertia::render('Admin/Assessments/Show', [
            'assessment' => [
                'id' => $assessment->id,
                'user' => $assessment->user ? [
                    'id' => $assessment->user->id,
                    'name' => $assessment->user->name,
                    'email' => $assessment->user->email,
                ] : null,
                'mode' => $assessment->mode,
                'status' => $assessment->status,
                'created_at' => $assessment->created_at->toDateTimeString(),
                'completed_at' => $assessment->completed_at?->toDateTimeString(),
            ],
            'answers' => $assessment->answers->map(fn ($a) => [
                'id' => $a->id,
                'question_text' => $a->question->question_text,
                'category_name' => $a->question->category?->name,
                'response_text' => $a->response_text,
            ]),
            'profile' => $assessment->vocationalProfile ? [
                'opening_synthesis' => $assessment->vocationalProfile->opening_synthesis,
                'vocational_orientation' => $assessment->vocationalProfile->vocational_orientation,
                'primary_pathways' => $assessment->vocationalProfile->primary_pathways,
                'specific_considerations' => $assessment->vocationalProfile->specific_considerations,
                'next_steps' => $assessment->vocationalProfile->next_steps,
                'primary_domain' => $assessment->vocationalProfile->primary_domain,
                'mode_of_work' => $assessment->vocationalProfile->mode_of_work,
            ] : null,
        ]);
    }
}
