<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Answer;
use App\Models\Assessment;
use App\Support\ConversationLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AssessmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:conversation,written',
            'organization_id' => 'nullable|uuid|exists:organizations,id',
            'locale' => 'nullable|string|max:16',
            'speech_locale' => 'nullable|string|max:16',
        ]);

        $locale = ConversationLocale::normalize($validated['locale'] ?? null);
        $speechLocale = ConversationLocale::normalize($validated['speech_locale'] ?? $locale);

        $assessment = Assessment::create([
            'user_id' => $request->user()?->id,
            'organization_id' => $validated['organization_id'] ?? null,
            'mode' => $validated['mode'],
            'status' => 'in_progress',
            'locale' => $locale,
            'speech_locale' => $speechLocale,
            'guest_token' => $request->user() ? null : Str::random(64),
            'started_at' => now(),
        ]);

        return response()->json(
            new AssessmentResource($assessment),
            201
        );
    }

    public function show(Request $request, Assessment $assessment): AssessmentResource
    {
        $this->authorizeAccess($request, $assessment);
        $assessment->load(['answers.question', 'vocationalProfile']);

        return new AssessmentResource($assessment);
    }

    public function saveAnswer(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $validated = $request->validate([
            'question_id' => 'required|uuid|exists:questions,id',
            'response_text' => 'nullable|string',
            'response_locale' => 'nullable|string|max:16',
        ]);

        $answer = Answer::updateOrCreate(
            [
                'assessment_id' => $assessment->id,
                'question_id' => $validated['question_id'],
            ],
            [
                'response_text' => $validated['response_text'],
                'response_locale' => ConversationLocale::normalize($validated['response_locale'] ?? $assessment->locale),
            ]
        );

        return response()->json(['id' => $answer->id], 200);
    }

    public function updateAnswer(Request $request, Assessment $assessment, Answer $answer): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $validated = $request->validate([
            'response_text' => 'nullable|string',
        ]);

        $answer->update($validated);

        return response()->json(['id' => $answer->id], 200);
    }

    public function complete(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $answerCount = $assessment->answers()->count();
        if ($answerCount === 0) {
            return response()->json(['error' => 'Cannot complete assessment with no answers.'], 422);
        }

        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        $this->dispatchAnalysisJob($assessment);

        return response()->json(['status' => 'analyzing']);
    }

    private function dispatchAnalysisJob(Assessment $assessment): void
    {
        $dispatchMode = (string) config('vocation.assessment.analysis_dispatch', 'queue');

        Log::info('assessment_analysis_dispatch_requested', [
            'assessment_id' => $assessment->id,
            'dispatch_mode' => $dispatchMode,
            'queue_connection' => config('queue.default'),
            'queue_name' => 'ai-analysis',
        ]);

        if ($dispatchMode === 'sync') {
            AnalyzeAssessmentJob::dispatchSync($assessment);

            return;
        }

        if ($dispatchMode === 'after_response') {
            AnalyzeAssessmentJob::dispatchAfterResponse($assessment);

            return;
        }

        AnalyzeAssessmentJob::dispatch($assessment);
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
