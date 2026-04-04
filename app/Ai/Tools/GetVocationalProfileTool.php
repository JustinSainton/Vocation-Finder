<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetVocationalProfileTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get the user\'s full vocational profile including primary domain, pathways, orientation, synthesis, and category scores. Use this to understand who they are vocationally.';
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
            return json_encode(['error' => 'No completed assessment found.']);
        }

        $profile = $assessment->vocationalProfile;

        return json_encode([
            'primary_domain' => $profile->primary_domain,
            'primary_pathways' => $profile->primary_pathways,
            'secondary_orientation' => $profile->secondary_orientation,
            'mode_of_work' => $profile->mode_of_work,
            'vocational_orientation' => $profile->vocational_orientation,
            'opening_synthesis' => $profile->opening_synthesis,
            'specific_considerations' => $profile->specific_considerations,
            'ministry_integration' => $profile->ministry_integration,
            'next_steps' => $profile->next_steps,
            'category_scores' => $profile->category_scores,
        ]);
    }
}
