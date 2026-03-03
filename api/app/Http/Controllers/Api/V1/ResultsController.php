<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VocationalProfileResource;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function show(Request $request, Assessment $assessment): VocationalProfileResource|JsonResponse
    {
        $profile = $assessment->vocationalProfile;

        if (! $profile) {
            return response()->json([
                'status' => $assessment->status,
                'message' => $assessment->status === 'analyzing'
                    ? 'Your assessment is being analyzed. Please check back shortly.'
                    : 'No results available yet.',
            ], $assessment->status === 'analyzing' ? 202 : 404);
        }

        return new VocationalProfileResource($profile);
    }

    public function email(Request $request, Assessment $assessment): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // TODO: Dispatch email job
        // EmailResultsJob::dispatch($assessment, $validated['email']);

        return response()->json(['message' => 'Results will be emailed shortly.']);
    }
}
