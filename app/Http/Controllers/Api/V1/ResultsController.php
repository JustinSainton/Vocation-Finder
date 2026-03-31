<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VocationalProfileResource;
use App\Jobs\EmailResultsJob;
use App\Models\Assessment;
use App\Services\ResultsPdf;
use App\Support\VocationalProfileCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResultsController extends Controller
{
    public function show(Request $request, Assessment $assessment): VocationalProfileResource|JsonResponse
    {
        $this->authorizeAccess($request, $assessment);
        $copy = VocationalProfileCopy::forLocale($assessment->locale);

        $profile = $assessment->vocationalProfile;

        if (! $profile) {
            $statusCode = match ($assessment->status) {
                'analyzing' => 202,
                'failed' => 500,
                default => 404,
            };

            return response()->json([
                'status' => $assessment->status,
                'message' => match ($assessment->status) {
                    'analyzing' => $copy['status_analyzing'],
                    'failed' => $copy['status_failed'],
                    default => $copy['status_missing'],
                },
            ], $statusCode);
        }

        $resource = new VocationalProfileResource($profile);

        $resource->additional([
            'disclaimer' => $copy['disclaimer'],
        ]);

        return $resource;
    }

    public function pdf(Request $request, Assessment $assessment, ResultsPdf $resultsPdf): Response|JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $profile = $assessment->vocationalProfile;
        if (! $profile) {
            return response()->json([
                'error' => VocationalProfileCopy::forLocale($assessment->locale)['results_not_ready'],
            ], 422);
        }

        return response($resultsPdf->render($profile), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultsPdf->filename($profile).'"',
        ]);
    }

    public function email(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $profile = $assessment->vocationalProfile;
        if (! $profile) {
            return response()->json([
                'error' => VocationalProfileCopy::forLocale($assessment->locale)['results_not_ready'],
            ], 422);
        }

        $this->dispatchEmailJob($assessment, $validated['email']);

        return response()->json([
            'message' => VocationalProfileCopy::forLocale($assessment->locale)['results_emailed'],
        ]);
    }

    protected function dispatchEmailJob(Assessment $assessment, string $email): void
    {
        $dispatchMode = (string) config('vocation.results.email_dispatch', 'after_response');

        if ($dispatchMode === 'sync') {
            EmailResultsJob::dispatchSync($assessment, $email);

            return;
        }

        if ($dispatchMode === 'queue') {
            EmailResultsJob::dispatch($assessment, $email);

            return;
        }

        EmailResultsJob::dispatchAfterResponse($assessment, $email);
    }

    private function authorizeAccess(Request $request, Assessment $assessment): void
    {
        $user = $request->user();

        if ($user && $assessment->user_id === $user->id) {
            return;
        }

        if (! $user && $request->header('X-Guest-Token') === $assessment->guest_token) {
            return;
        }

        abort(403, 'Unauthorized access to assessment.');
    }
}
