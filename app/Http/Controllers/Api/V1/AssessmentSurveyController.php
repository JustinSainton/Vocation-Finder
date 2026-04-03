<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentSurveyController extends Controller
{
    public function store(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAccess($request, $assessment);

        $validated = $request->validate([
            'type' => 'required|in:before,after',
            'clarity_score' => 'required|integer|min:1|max:10',
            'action_score' => 'required|integer|min:1|max:10',
        ]);

        $survey = AssessmentSurvey::create([
            'assessment_id' => $assessment->id,
            'type' => $validated['type'],
            'clarity_score' => $validated['clarity_score'],
            'action_score' => $validated['action_score'],
        ]);

        return response()->json(['id' => $survey->id], 201);
    }

    private function authorizeAccess(Request $request, Assessment $assessment): void
    {
        $user = $request->user();

        if ($user && $assessment->user_id === $user->id) {
            return;
        }

        if (! $user && $assessment->guest_token && hash_equals($assessment->guest_token, (string) $request->header('X-Guest-Token'))) {
            return;
        }

        abort(403, 'Unauthorized access to assessment.');
    }
}
