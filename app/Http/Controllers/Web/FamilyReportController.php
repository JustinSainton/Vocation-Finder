<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ParentConsent;
use App\Support\ParentVisibility;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a parent sees: progress and milestones, and one thing to actually do.
 *
 * Addressed by the consent token and deliberately outside `auth`, for the same
 * reason the consent page is: requiring a parent to create an account adds a
 * step that loses exactly the families who are busiest. The token is the same
 * 64 characters they already have, and revoking consent closes this door with
 * it.
 *
 * Every field on this page comes from {@see ParentVisibility}, which is the
 * only thing in the codebase permitted to decide what crosses this line. The
 * controller does no assembling of its own — a second place that decides what
 * a parent sees is a second place that can get it wrong, and the invariant
 * being protected is that a student who knows their parent is reading
 * everything will not be honest with the coach.
 */
class FamilyReportController extends Controller
{
    public function show(string $token): Response
    {
        $consent = ParentConsent::query()->where('token', $token)->firstOrFail();

        abort_unless($consent->isGranted(), 403, 'This link is no longer active.');

        return Inertia::render('Family/Report', [
            'parent_name' => $consent->parent_name,
            'summary' => ParentVisibility::summaryFor($consent->user),
        ]);
    }
}
