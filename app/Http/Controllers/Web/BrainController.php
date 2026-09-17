<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentPlace;
use App\Http\Controllers\Controller;
use App\Models\BrainEntry;
use App\Support\BrainRetrieval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vocational brain, read on a page instead of downloaded as a file.
 *
 * This controller sits outside `feature:pathway_coach` and performs no
 * entitlement, consent or subscription check, for the same reason
 * {@see BrainExportController} performs none: a lapse freezes the brain, it
 * does not seize it. Showing a student a paywall in front of sentences they
 * wrote is the one thing this product promised a parent it would never do.
 *
 * Nothing here is summarised, titled or grouped into themes. The page shows
 * dated quotes and a count. The brain surfaces what the student already said;
 * it does not write what they would have said.
 */
class BrainController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $topic = trim((string) $request->query('topic', ''));
        $retrieval = new BrainRetrieval;

        $entries = $topic === ''
            ? $retrieval->recent($user, 50)
            : $retrieval->search($user, $topic, 50);

        return Inertia::render('Brain/Index', [
            'blurb' => StudentPlace::Brain->blurb(),
            'topic' => $topic,
            'total' => $user->brainEntries()->count(),
            'entries' => $entries
                ->map(fn (BrainEntry $entry) => [
                    'id' => $entry->id,
                    'said_on' => $entry->occurred_at->toDateString(),
                    'context' => $entry->context,
                    'in_their_words' => $entry->content,
                    'source' => $entry->source->label(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
