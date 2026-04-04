<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCareerProfileTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get the user\'s career profile including work history, education, skills, certifications, and volunteer experience. Use this to understand their professional background.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $profile = $this->user->careerProfile;

        if (! $profile) {
            return json_encode(['error' => 'No career profile found. The user has not entered their career history yet.']);
        }

        return json_encode([
            'work_history' => $profile->work_history,
            'education' => $profile->education,
            'skills' => $profile->skills,
            'certifications' => $profile->certifications,
            'volunteer' => $profile->volunteer,
            'import_source' => $profile->import_source,
        ]);
    }
}
