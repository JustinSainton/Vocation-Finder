<?php

namespace App\Ai\Tools;

use App\Enums\GapType;
use App\Models\Gap;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lets the coach turn a conversation into a record.
 *
 * Without this the refinement conversation evaporates: the coach works out
 * what is actually in a student's way, says something useful about it, and
 * the insight dies with the session. The gap is what everything downstream
 * aims at, so it has to outlive the turn that produced it.
 *
 * `type` is enum-constrained to the six. A free-text gap type would drift into
 * a seventh and an eighth within a week, and nothing could be aimed at them.
 */
class RecordGapTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Record a gap you have identified between this student and their next step. Use this once you are reasonably sure which of the six gaps is live, not on a first guess.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema
                ->string()
                ->enum(GapType::values())
                ->description('Which of the six gaps this is')
                ->required(),
            'summary' => $schema
                ->string()
                ->description('What is in their way, in one plain sentence, written to them rather than about them')
                ->required(),
            'evidence' => $schema
                ->string()
                ->description('What they said that showed you this. Their words, not your paraphrase.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $type = GapType::tryFrom((string) $request['type']);
        $summary = trim((string) $request['summary']);

        if (! $type || $summary === '') {
            return json_encode([
                'recorded' => false,
                'guidance' => 'A gap needs one of the six types and a plain summary. Nothing was saved.',
            ]);
        }

        $assessment = $this->user->assessments()->where('status', 'completed')->latest()->first();

        // The same gap named twice in one conversation is one gap. Re-recording
        // it would inflate a student's list of problems without adding one.
        $existing = $this->user->gaps()->active()->ofType($type)->first();

        if ($existing) {
            return json_encode([
                'recorded' => false,
                'gap_id' => $existing->id,
                'guidance' => "A {$type->label()} gap is already open for this student. Work on that one rather than opening another.",
            ]);
        }

        $gap = Gap::create([
            'user_id' => $this->user->id,
            'assessment_id' => $assessment?->id,
            'type' => $type,
            'summary' => $summary,
            'evidence' => trim((string) $request['evidence']) ?: null,
            'source' => 'coach',
        ]);

        return json_encode([
            'recorded' => true,
            'gap_id' => $gap->id,
            'type' => $type->value,
            'closing_move' => $type->closingMove(),
            'guidance' => 'Now give them one thing to do that would close this gap. One, not a list.',
        ]);
    }
}
