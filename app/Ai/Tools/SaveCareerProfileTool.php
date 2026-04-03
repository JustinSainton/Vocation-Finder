<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SaveCareerProfileTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Save or update the user\'s career profile with structured work history, education, skills, certifications, and volunteer experience. Call this as you gather information during the conversation.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_history' => $schema->array('Work experience entries')->nullable(),
            'education' => $schema->array('Education entries')->nullable(),
            'skills' => $schema->array('Skills list')->nullable(),
            'certifications' => $schema->array('Certifications list')->nullable(),
            'volunteer' => $schema->array('Volunteer experience entries')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $data = array_filter([
            'work_history' => $request['work_history'] ?? null,
            'education' => $request['education'] ?? null,
            'skills' => $request['skills'] ?? null,
            'certifications' => $request['certifications'] ?? null,
            'volunteer' => $request['volunteer'] ?? null,
        ], fn ($v) => $v !== null);

        $profile = $this->user->careerProfile;

        if ($profile) {
            $profile->update(array_merge($data, ['import_source' => 'conversation']));
        } else {
            $this->user->careerProfile()->create(array_merge($data, ['import_source' => 'conversation']));
        }

        return json_encode(['saved' => true, 'sections_updated' => array_keys($data)]);
    }
}
