<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResumeJob;
use App\Models\ResumeVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $resumes = $request->user()->resumeVersions()
            ->with('jobListing:id,title,company_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($resumes);
    }

    public function show(Request $request, ResumeVersion $resumeVersion): JsonResponse
    {
        if ($resumeVersion->user_id !== $request->user()->id) {
            abort(403);
        }

        $resumeVersion->load('jobListing:id,title,company_name');

        return response()->json(['data' => $resumeVersion]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_listing_id' => ['required', 'uuid', 'exists:job_listings,id'],
        ]);

        $user = $request->user();

        // Find the user's latest completed assessment
        $assessment = $user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();

        $resume = ResumeVersion::create([
            'user_id' => $user->id,
            'job_listing_id' => $validated['job_listing_id'],
            'assessment_id' => $assessment?->id,
            'resume_data' => [],
            'status' => 'generating',
        ]);

        GenerateResumeJob::dispatch($resume);

        return response()->json([
            'data' => $resume,
            'message' => 'Resume generation started.',
        ], 202);
    }

    public function download(Request $request, ResumeVersion $resumeVersion): JsonResponse
    {
        if ($resumeVersion->user_id !== $request->user()->id) {
            abort(403);
        }

        $format = $request->query('format', 'pdf');
        $path = $format === 'docx' ? $resumeVersion->file_path_docx : $resumeVersion->file_path_pdf;

        if (! $path) {
            return response()->json(['message' => 'File not available yet.'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($path, now()->addHours(1));

        return response()->json(['url' => $url]);
    }

    public function destroy(Request $request, ResumeVersion $resumeVersion): JsonResponse
    {
        if ($resumeVersion->user_id !== $request->user()->id) {
            abort(403);
        }

        $resumeVersion->delete();

        return response()->json(null, 204);
    }
}
