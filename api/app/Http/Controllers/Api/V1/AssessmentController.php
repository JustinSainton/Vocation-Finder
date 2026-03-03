<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\Answer;
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
        ]);

        $assessment = Assessment::create([
            'user_id' => $request->user()?->id,
            'organization_id' => $validated['organization_id'] ?? null,
            'mode' => $validated['mode'],
            'status' => 'in_progress',
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
        ]);

        $answer = Answer::updateOrCreate(
            [
                'assessment_id' => $assessment->id,
                'question_id' => $validated['question_id'],
            ],
            [
                'response_text' => $validated['response_text'],
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

        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        // Dispatch AI analysis job
        // AnalyzeAssessmentJob::dispatch($assessment);

        return response()->json(['status' => 'analyzing']);
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
