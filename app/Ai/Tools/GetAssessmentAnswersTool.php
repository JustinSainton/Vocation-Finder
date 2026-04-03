<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAssessmentAnswersTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Retrieve the user\'s vocational assessment profile including their primary pathways, domain, orientation, and category scores. Use this to inform resume questions and understand their calling.';
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
            'vocational_orientation' => $profile->vocational_orientation,
            'opening_synthesis' => $profile->opening_synthesis,
            'specific_considerations' => $profile->specific_considerations,
            'next_steps' => $profile->next_steps,
        ]);
    }
}
