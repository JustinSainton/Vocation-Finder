<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSurvey;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function orientation(): Response
    {
        return Inertia::render('Assessment/Orientation');
    }

    public function before(): Response
    {
        return Inertia::render('Assessment/Before');
    }

    public function storeBefore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clarity_score' => 'required|integer|min:1|max:10',
            'action_score' => 'required|integer|min:1|max:10',
        ]);

        $assessment = Assessment::create([
            'user_id' => $request->user()?->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => $request->user() ? null : Str::random(64),
            'started_at' => now(),
        ]);

        AssessmentSurvey::create([
            'assessment_id' => $assessment->id,
            'type' => 'before',
            'clarity_score' => $validated['clarity_score'],
            'action_score' => $validated['action_score'],
        ]);

        $request->session()->put('current_assessment_id', $assessment->id);

        return redirect('/assessment/written');
    }

    public function written(Request $request): Response
    {
        $betaEnabled = config('vocation.beta.questions_enabled');

        $questions = Question::with('category')
            ->where('is_beta', $betaEnabled)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Question $q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'category_name' => $q->category?->name,
                'sort_order' => $q->sort_order,
            ]);

        // Re-use existing assessment from before survey, or create a new one
        $assessmentId = $request->session()->get('current_assessment_id');
        $assessment = $assessmentId ? Assessment::find($assessmentId) : null;

        if (! $assessment) {
            $assessment = Assessment::create([
                'user_id' => $request->user()?->id,
                'mode' => 'written',
                'status' => 'in_progress',
                'guest_token' => $request->user() ? null : Str::random(64),
                'started_at' => now(),
            ]);
        }

        return Inertia::render('Assessment/Written', [
            'questions' => $questions,
            'assessment_id' => $assessment->id,
            'guest_token' => $assessment->guest_token,
        ]);
    }

    public function after(Request $request, Assessment $assessment): Response
    {
        return Inertia::render('Assessment/After', [
            'assessment_id' => $assessment->id,
            'guest_token' => $assessment->guest_token,
        ]);
    }

    public function storeAfter(Request $request, Assessment $assessment): RedirectResponse
    {
        $validated = $request->validate([
            'clarity_score' => 'required|integer|min:1|max:10',
            'action_score' => 'required|integer|min:1|max:10',
        ]);

        AssessmentSurvey::create([
            'assessment_id' => $assessment->id,
            'type' => 'after',
            'clarity_score' => $validated['clarity_score'],
            'action_score' => $validated['action_score'],
        ]);

        $request->session()->forget('current_assessment_id');

        return redirect("/assessment/{$assessment->id}/results");
    }

    public function results(Request $request, Assessment $assessment): Response
    {
        $profile = $assessment->vocationalProfile;

        $profileData = null;
        if ($profile) {
            $profileData = [
                'id' => $profile->id,
                'opening_synthesis' => $profile->opening_synthesis,
                'vocational_orientation' => $profile->vocational_orientation,
                'primary_pathways' => $profile->primary_pathways,
                'specific_considerations' => $profile->specific_considerations,
                'next_steps' => $profile->next_steps,
                'ministry_integration' => $profile->ministry_integration,
                'primary_domain' => $profile->primary_domain,
                'mode_of_work' => $profile->mode_of_work,
                'secondary_orientation' => $profile->secondary_orientation,
            ];
        }

        $user = $assessment->user;
        $isPaid = $user && $user->subscribed();

        // Check for curriculum pathway
        $pathway = $assessment->curriculumPathway;
        $pathwayData = $pathway ? [
            'id' => $pathway->id,
            'status' => $pathway->status,
        ] : null;

        return Inertia::render('Assessment/Results', [
            'assessment_id' => $assessment->id,
            'guest_token' => $assessment->guest_token,
            'status' => $assessment->status,
            'profile' => $profileData,
            'tier' => $isPaid ? 'paid' : 'free',
            'upgrade_message' => $isPaid ? null : 'Unlock your complete vocational profile — including specific career pathways, personalized considerations, and actionable next steps.',
            'pathway' => $pathwayData,
        ]);
    }
}
