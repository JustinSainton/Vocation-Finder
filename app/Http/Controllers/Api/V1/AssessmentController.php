<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Answer;
use App\Models\Assessment;
use App\Support\AnalysisLogger;
use App\Support\AssessmentAccess;
use App\Support\AssessmentAnalysisDispatcher;
use App\Support\ConversationLocale;
use App\Support\CrisisCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        AssessmentAccess::authorizeReading($request, $assessment);
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
                // Nullable fields are omitted from $validated when the client
                // does not send the key at all — coalesce so updateOrCreate
                // never throws on a missing response_text.
                'response_text' => $validated['response_text'] ?? null,
                'response_locale' => ConversationLocale::normalize($validated['response_locale'] ?? $assessment->locale),
            ]
        );

        /*
         | The assessment is the other place a student says something that
         | cannot wait for a portrait. The answer is still saved — refusing to
         | store it would throw away what they wrote — but the support block
         | travels back with the save, so it reaches them while they are still
         | on the question rather than in an analysis twenty minutes later.
         |
         | Same rule as the coach: {@see CrisisCheck} runs before any
         | interpretation, and nobody is notified.
         */
        $crisis = (new CrisisCheck)->standing((string) ($validated['response_text'] ?? ''));

        return response()->json(array_filter([
            'id' => $answer->id,
            'support' => $crisis->isEscalation()
                ? (new CrisisCheck)->support($answer->response_locale)
                : null,
        ]), 200);
    }

    public function updateAnswer(Request $request, Assessment $assessment, Answer $answer): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $validated = $request->validate([
            'response_text' => 'nullable|string',
        ]);

        $answer->update([
            'response_text' => $validated['response_text'] ?? null,
        ]);

        return response()->json(['id' => $answer->id], 200);
    }

    public function complete(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $answerCount = $assessment->answers()->count();
        if ($answerCount === 0) {
            return response()->json(['error' => 'Cannot complete assessment with no answers.'], 422);
        }

        $previousStatus = $assessment->status;

        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        AnalysisLogger::statusTransition(
            $assessment->id,
            $assessment->user_id,
            $previousStatus,
            'analyzing',
        );

        AssessmentAnalysisDispatcher::dispatch($assessment, source: 'assessment_api');

        return response()->json(['status' => 'analyzing']);
    }

    private function authorizeAccess(Request $request, Assessment $assessment): void
    {
        AssessmentAccess::authorize($request, $assessment);
    }
}
