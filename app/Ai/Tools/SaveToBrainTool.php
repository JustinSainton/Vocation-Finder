<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\BrainCapture;
use App\Support\SignalExtractor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

/**
 * Keeps something a student just said, on purpose.
 *
 * Most capture is automatic. This tool exists for the moment a student says
 * "save that" — and for the moment the coach recognises that a sentence just
 * spoken is the truest thing said all session.
 *
 * The guard is the same one that makes signal extraction trustworthy: the text
 * being saved must appear in what the student actually wrote this turn. A tool
 * that accepts free text would let the model write the student's memory for
 * them in the most convincing way possible — a tidied, articulate version of
 * their sentence, filed under their name. That is the single failure the brain
 * cannot survive, so it is checked rather than instructed.
 */
class SaveToBrainTool implements Tool
{
    public function __construct(
        private User $user,
        private ?string $studentMessage = null,
    ) {}

    public function description(): string
    {
        return 'Keep something the student just said, word for word, so they can find it again later. Copy their sentence exactly; do not rewrite it.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_words' => $schema->string()
                ->description('The student\'s own sentence, copied character for character from what they just wrote. Not a summary, not a tidied version, not your paraphrase of it.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $words = trim((string) $request['student_words']);

        if ($this->studentMessage !== null && ! SignalExtractor::spanAppearsIn($words, $this->studentMessage)) {
            return json_encode([
                'saved' => false,
                'guidance' => 'That is not what they wrote. The brain only holds their own words, so copy the sentence exactly as they typed it and try again.',
            ]);
        }

        try {
            $entry = (new BrainCapture)->captureDirect($this->user, $words);
        } catch (RuntimeException $exception) {
            return json_encode([
                'saved' => false,
                'guidance' => $exception->getMessage(),
            ]);
        }

        return json_encode([
            'saved' => true,
            'in_their_words' => $entry->content,
            'guidance' => 'Kept. Tell them it is saved and move on — do not read it back to them as though it were your idea.',
        ]);
    }
}
