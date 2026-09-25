<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\CoachAssessmentContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Every question the student answered and what they wrote.
 *
 * Distinct from {@see GetPathwayProfileTool}, which is the system's reading of
 * those answers, and from {@see GetStudentSignalsTool}, which is only the
 * verified spans the signal pass kept. The coach needs all three: the portrait,
 * the full responses, and the quotable spans.
 */
class GetAssessmentResponsesTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get every question this student answered in their assessment and the response they wrote. Call this when you need the full picture of what they said, not just the portrait summary or extracted signals.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $context = new CoachAssessmentContext;
        $payload = $context->payload($this->user);

        if ($payload === null) {
            return json_encode([
                'has_responses' => false,
                'guidance' => 'This student has not completed an assessment yet. Ask them about themselves instead of guessing.',
            ]);
        }

        return json_encode([
            'has_responses' => true,
            'assessment_id' => $payload['assessment_id'],
            'responses' => $payload['responses'],
            'guidance' => 'These are their actual answers. Be specific about what they wrote before you ask for anything new.',
        ]);
    }
}
