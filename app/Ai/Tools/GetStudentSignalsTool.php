<?php

namespace App\Ai\Tools;

use App\Models\SignalExtraction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The student's own words, as verified Layer 4 signals.
 *
 * This is the tool that makes "surfaces what the student already said; it does
 * not write what they would have said" achievable. Every `said` value here has
 * been checked as a literal span of an answer the student wrote, so a coach
 * quoting from this tool cannot invent a quote — and a coach with no such tool
 * inevitably will, because being specific is what it is being asked for.
 *
 * Aspiration and demonstrated evidence are returned separately because 10.3
 * turns on the distance between them.
 */
class GetStudentSignalsTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Get the specific things this student actually said in their assessment, grouped into what they have done (demonstrated) and what they want (aspiration). Use this whenever you want to be specific about them, and quote only from here.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $assessment = $this->user->assessments()
            ->where('status', 'completed')
            ->latest()
            ->first();

        $signals = $assessment?->signalExtractions ?? collect();

        if ($signals->isEmpty()) {
            return json_encode([
                'signals' => [],
                'guidance' => 'Nothing has been extracted for this student. Do not invent specifics or quotes. Ask them directly instead.',
            ]);
        }

        $grouped = $signals
            ->groupBy(fn (SignalExtraction $signal) => $signal->track->value)
            ->map(fn ($group) => $group->map(fn (SignalExtraction $signal) => array_filter([
                'type' => $signal->type->value,
                'observation' => $signal->content,
                'said' => $signal->verbatim,
                'constraint_nature' => $signal->constraint_nature,
            ], fn ($value) => $value !== null))->values());

        return json_encode([
            'demonstrated' => $grouped->get('demonstrated', []),
            'aspiration' => $grouped->get('aspiration', []),
            'guidance' => 'Quote only from the "said" values. They are this student\'s literal words. Never paraphrase one and present it as a quote.',
        ]);
    }
}
