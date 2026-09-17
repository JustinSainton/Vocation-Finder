<?php

namespace App\Http\Controllers\Web;

use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\ClarityCheck;
use App\Support\AssessmentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Roadmap 5.1 — the before and after reading.
 *
 * Open to guests for the same reason {@see EvaluationFeedbackController} is:
 * requiring an account first would sample only the people it worked for.
 */
class ClarityCheckController extends Controller
{
    public function store(Request $request, Assessment $assessment): RedirectResponse
    {
        AssessmentAccess::authorize($request, $assessment);

        $validated = $request->validate([
            'moment' => ['required', Rule::in(ClarityMoment::values())],
            'standing' => ['required', Rule::in(ClarityStanding::values())],
            'guest_token' => ['nullable', 'string'],
        ]);

        $moment = ClarityMoment::from($validated['moment']);

        $this->refuseAReadingTakenAtTheWrongEnd($assessment, $moment);

        /*
         | The first reading stands. A student who answers, reads their
         | portrait and then revises the before-reading has let the result
         | edit its own baseline — and the append-only guard on the model
         | would throw rather than quietly accept it.
         */
        ClarityCheck::firstOrCreate(
            ['assessment_id' => $assessment->id, 'moment' => $moment],
            ['user_id' => $assessment->user_id, 'standing' => $validated['standing']],
        );

        return back();
    }

    /**
     * A "before" is only a before if it was taken before.
     *
     * Once any answer exists, the reading is a recollection of how lost
     * somebody felt, and people misremember that in the direction that makes
     * the intervening thing look good. An "after" before there is anything to
     * read is the same error pointing the other way.
     */
    protected function refuseAReadingTakenAtTheWrongEnd(Assessment $assessment, ClarityMoment $moment): void
    {
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
    }
}
