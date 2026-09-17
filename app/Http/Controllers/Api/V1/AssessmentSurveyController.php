<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSurvey;
use App\Support\AssessmentAccess;
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
        AssessmentAccess::authorize($request, $assessment);
    }
}
