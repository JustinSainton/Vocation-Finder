<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ReviewDimension;
use App\Enums\ReviewStanding;
use App\Http\Controllers\Controller;
use App\Models\PortraitReview;
use App\Models\VocationalProfile;
use App\Support\ReviewQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roadmap 5.3 — human review of portraits.
 *
 * There is deliberately **no route that opens a chosen portrait**. The
 * reviewer is handed the next one by {@see ReviewQueue}, because a reviewer
 * who picks reads the interesting ones, and the failure mode this product
 * actually has is the fluent, plausible, slightly-wrong portrait nobody
 * volunteers for.
 *
 * The student's own answers are shown beside the portrait, because eight of
 * the nine dimensions cannot be judged without them — "could this have been
 * written about somebody else" is unanswerable from the portrait alone.
 */
class AdminPortraitReviewController extends Controller
{
    public function __construct(protected ReviewQueue $queue = new ReviewQueue) {}

    public function index(Request $request): Response
    {
        $profile = $this->queue->next($request->user());

        return Inertia::render('Admin/PortraitReview', [
            'coverage' => $this->queue->coverage(),
            'dimensions' => collect(ReviewDimension::cases())->map(fn (ReviewDimension $dimension) => [
                'value' => $dimension->value,
                'label' => $dimension->label(),
                'question' => $dimension->question(),
            ])->all(),
            'standings' => collect(ReviewStanding::cases())->map(fn (ReviewStanding $standing) => [
                'value' => $standing->value,
                'label' => $standing->label(),
            ])->all(),
            'portrait' => $profile ? [
                'id' => $profile->id,
                'opening_synthesis' => $profile->opening_synthesis,
                'vocational_orientation' => $profile->vocational_orientation,
                'primary_pathways' => $profile->primary_pathways,
                'next_steps' => $profile->next_steps,
                'confidence_level' => $profile->confidence_level?->value,
                'prompt_version' => $profile->prompt_version,
                'answers' => $profile->assessment?->answers()
                    ->with('question')
                    ->get()
                    ->map(fn ($answer) => [
                        'question' => $answer->question?->question_text,
                        'response' => $answer->response_text,
                    ])->values()->all() ?? [],
            ] : null,
        ]);
    }

    public function store(Request $request, VocationalProfile $vocationalProfile): RedirectResponse
    {
        $validated = $request->validate([
            'dimension' => ['required', Rule::in(ReviewDimension::values())],
            'standing' => ['required', Rule::in(ReviewStanding::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        PortraitReview::firstOrCreate(
            [
                'vocational_profile_id' => $vocationalProfile->id,
                'reviewer_id' => $request->user()->id,
                'dimension' => $validated['dimension'],
            ],
            [
                'standing' => $validated['standing'],
                'note' => $validated['note'] ?? null,
                'prompt_version' => $vocationalProfile->prompt_version,
            ],
        );

        return back();
    }
}
