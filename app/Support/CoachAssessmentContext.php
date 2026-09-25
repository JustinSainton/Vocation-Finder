<?php

namespace App\Support;

use App\Models\Answer;
use App\Models\Assessment;
use App\Models\User;

/**
 * Everything the coach needs from the student's latest portrait assessment,
 * assembled server-side so the first turns are grounded even when the model
 * skips tool calls.
 */
class CoachAssessmentContext
{
    public function assessment(User $user): ?Assessment
    {
        return (new PathwayProfileReadiness)->latestPortraitAssessment($user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(User $user): ?array
    {
        $assessment = $this->assessment($user)?->load(['vocationalProfile', 'signalExtractions']);

        if (! $assessment?->vocationalProfile) {
            return null;
        }

        $profile = $assessment->vocationalProfile;
        $confidence = $profile->confidence_level;

        return [
            'assessment_id' => (string) $assessment->id,
            'completed_at' => $assessment->completed_at?->toDateString(),
            'profile' => array_filter([
                'confidence' => $confidence?->value,
                'confidence_label' => $confidence?->label(),
                'confidence_rationale' => $profile->confidence_rationale,
                'missing_evidence' => $profile->missing_evidence,
                'evidence_gap' => $profile->evidence_gap,
                'may_name_a_direction' => $confidence?->permitsConclusion() ?? false,
                'primary_domain' => $profile->primary_domain,
                'primary_pathways' => $profile->primary_pathways,
                'opening_synthesis' => $profile->opening_synthesis,
                'specific_considerations' => $profile->specific_considerations,
            ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            'responses' => $this->responses($assessment),
            'signals' => $this->signals($assessment),
        ];
    }

    /**
     * A block for the system prompt on early exchanges.
     */
    public function promptSection(User $user): ?string
    {
        $payload = $this->payload($user);

        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<TEXT
        ## Assessment context (already loaded)

        The student completed their assessment. This is the portrait and every
        answer they gave. Read it before you speak. Do not ask them to repeat
        something that is already here unless you are testing a specific detail.
        Quote their words only from `responses` or `signals`; never invent a quote.

        ```json
        {$json}
        ```
        TEXT;
    }

    /**
     * @return list<array{question: string, response: string}>
     */
    public function responses(Assessment $assessment): array
    {
        return $assessment->answers()
            ->with('question')
            ->orderBy('id')
            ->get()
            ->map(fn (Answer $answer) => [
                'question' => trim((string) ($answer->question?->question_text ?? '')),
                'response' => trim((string) ($answer->response_text ?: $answer->audio_transcript)),
            ])
            ->filter(fn (array $row) => $row['response'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{demonstrated: list<array<string, mixed>>, aspiration: list<array<string, mixed>>}
     */
    public function signals(Assessment $assessment): array
    {
        $grouped = $assessment->signalExtractions
            ->groupBy(fn ($signal) => $signal->track->value)
            ->map(fn ($group) => $group->map(fn ($signal) => array_filter([
                'type' => $signal->type->value,
                'observation' => $signal->content,
                'said' => $signal->verbatim,
            ], fn ($value) => $value !== null))->values());

        return [
            'demonstrated' => $grouped->get('demonstrated', collect())->all(),
            'aspiration' => $grouped->get('aspiration', collect())->all(),
        ];
    }
}
