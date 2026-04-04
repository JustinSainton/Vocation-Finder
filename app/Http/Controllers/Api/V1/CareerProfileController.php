<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ParseResumeUploadJob;
use App\Models\CareerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->careerProfile;

        if (! $profile) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $profile]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_history' => ['nullable', 'array'],
            'education' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'certifications' => ['nullable', 'array'],
            'volunteer' => ['nullable', 'array'],
        ]);

        $profile = $request->user()->careerProfile;

        if ($profile) {
            $profile->update($validated);
        } else {
            $profile = $request->user()->careerProfile()->create([
                ...$validated,
                'import_source' => 'manual',
            ]);
        }

        return response()->json(['data' => $profile]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('file')->store(
            "career-profiles/{$request->user()->id}",
            's3'
        );

        $profile = $request->user()->careerProfile;

        if ($profile) {
            $profile->update([
                'raw_import_data' => ['file_path' => $path],
                'import_source' => 'pdf_upload',
                'imported_at' => now(),
            ]);
        } else {
            $profile = $request->user()->careerProfile()->create([
                'raw_import_data' => ['file_path' => $path],
                'import_source' => 'pdf_upload',
                'imported_at' => now(),
            ]);
        }

        ParseResumeUploadJob::dispatch($profile);

        return response()->json([
            'data' => $profile,
            'message' => 'Resume uploaded and parsing started.',
        ], 202);
    }

    public function destroy(Request $request): JsonResponse
    {
        $profile = $request->user()->careerProfile;

        if ($profile) {
            $profile->delete();
        }

        return response()->json(null, 204);
    }
}
