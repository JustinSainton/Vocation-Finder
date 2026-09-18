<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\ClarityCheck;
use App\Support\AssessmentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentClarityController extends Controller
{
    public function store(Request $request, Assessment $assessment): JsonResponse
    {
        AssessmentAccess::authorize($request, $assessment);

        $validated = $request->validate([
            'moment' => ['required', Rule::in(ClarityMoment::values())],
            'standing' => ['required', Rule::in(ClarityStanding::values())],
        ]);

        $moment = ClarityMoment::from($validated['moment']);

        if ($moment === ClarityMoment::Before && $assessment->answers()->exists()) {
            throw ValidationException::withMessages([
                'moment' => 'A first reading has to come before the questions do.',
            ]);
        }

        if ($moment === ClarityMoment::After && $assessment->vocationalProfile === null) {
            throw ValidationException::withMessages([
                'moment' => 'There is nothing to have become clearer about yet.',
            ]);
        }

        $check = ClarityCheck::firstOrCreate(
            ['assessment_id' => $assessment->id, 'moment' => $moment],
            ['user_id' => $assessment->user_id, 'standing' => $validated['standing']],
        );

        return response()->json(['id' => $check->id], 201);
    }
}
