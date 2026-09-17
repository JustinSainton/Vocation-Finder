<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The student's profile, including how much the system trusts it.
 *
 * Deliberately distinct from {@see GetVocationalProfileTool}, which returns
 * the profile without its confidence. A coach handed a profile and no
 * confidence will speak about it with uniform certainty, which is the
 * identity-foreclosure failure blueprint 11 is most concerned about. Here the
 * confidence travels with the content so it cannot be read without it.
 */
class GetPathwayProfileTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get this student\'s vocational profile together with how confident the system is in it, why, and what evidence is still missing. Always call this before discussing their direction.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $assessment = $this->user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();

        if (! $assessment) {
            return json_encode([
                'has_profile' => false,
                'guidance' => 'This student has not completed an assessment yet. Do not guess at their direction. Ask them about themselves and help them get started.',
            ]);
        }

        $profile = $assessment->vocationalProfile;
        $confidence = $profile->confidence_level;

        return json_encode([
            'has_profile' => true,
            'confidence' => $confidence?->value,
            'confidence_label' => $confidence?->label(),
            'confidence_rationale' => $profile->confidence_rationale,
            'missing_evidence' => $profile->missing_evidence,
            // Blueprint 10.3. The coach needs this to tell a student who has
            // done the work from one who has only imagined it — the same
            // encouragement serves neither.
            'evidence_gap' => $profile->evidence_gap,
            'may_name_a_direction' => $confidence?->permitsConclusion() ?? false,
            'primary_domain' => $profile->primary_domain,
            'primary_pathways' => $profile->primary_pathways,
            'secondary_orientation' => $profile->secondary_orientation,
            'mode_of_work' => $profile->mode_of_work,
            'vocational_orientation' => $profile->vocational_orientation,
            'opening_synthesis' => $profile->opening_synthesis,
            'specific_considerations' => $profile->specific_considerations,
            'next_steps' => $profile->next_steps,
            'category_scores' => $profile->category_scores,
        ]);
    }
}
