<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCoverLetterJob;
use App\Models\CoverLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoverLetterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $letters = $request->user()->coverLetters()
            ->with('jobListing:id,title,company_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($letters);
    }

    public function show(Request $request, CoverLetter $coverLetter): JsonResponse
    {
        if ($coverLetter->user_id !== $request->user()->id) {
            abort(403);
        }

        $coverLetter->load('jobListing:id,title,company_name');

        return response()->json(['data' => $coverLetter]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_listing_id' => ['required', 'uuid', 'exists:job_listings,id'],
        ]);

        $user = $request->user();

        $assessment = $user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();

        $coverLetter = CoverLetter::create([
            'user_id' => $user->id,
            'job_listing_id' => $validated['job_listing_id'],
            'assessment_id' => $assessment?->id,
            'status' => 'generating',
        ]);

        GenerateCoverLetterJob::dispatch($coverLetter);

        return response()->json([
            'data' => $coverLetter,
            'message' => 'Cover letter generation started.',
        ], 202);
    }

    public function download(Request $request, CoverLetter $coverLetter): JsonResponse
    {
        if ($coverLetter->user_id !== $request->user()->id) {
            abort(403);
        }

        $path = $coverLetter->file_path_pdf;

        if (! $path) {
            return response()->json(['message' => 'File not available yet.'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($path, now()->addHours(1));

        return response()->json(['url' => $url]);
    }

    public function destroy(Request $request, CoverLetter $coverLetter): JsonResponse
    {
        if ($coverLetter->user_id !== $request->user()->id) {
            abort(403);
        }

        $coverLetter->delete();

        return response()->json(null, 204);
    }
}
