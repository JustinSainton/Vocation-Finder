<?php

namespace App\Ai\Tools;

use App\Models\BrainEntry;
use App\Models\User;
use App\Support\BrainRetrieval;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What this student has said before about something.
 *
 * This is the payoff the whole brain exists for: a senior writing a college
 * essay, or a student wavering in month four, being handed the thing they
 * themselves said in month one. The coach cannot produce that from memory —
 * its context window holds this conversation, not this year — so without a
 * tool it will confabulate a plausible past instead.
 */
class SearchBrainTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Look up what this student has said before about a topic, in their own words, from across their assessment, past conversations and finished actions. Use this whenever you want to remind them of something they told you earlier.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()
                ->description('The subject to look for, in a few words — for example "working with kids" or "money".')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $entries = (new BrainRetrieval)->search($this->user, (string) $request['topic']);

        if ($entries->isEmpty()) {
            return json_encode([
                'found' => false,
                'guidance' => 'They have not said anything about this yet. Do not invent something they might have said — ask them now instead.',
            ]);
        }

        return json_encode([
            'found' => true,
            'entries' => $entries->map(fn (BrainEntry $entry) => array_filter([
                'in_their_words' => $entry->content,
                'said_on' => $entry->occurred_at->toDateString(),
                'context' => $entry->context,
                'source' => $entry->source->label(),
            ], fn ($value) => $value !== null))->values(),
            'guidance' => 'These are their words, not yours. Quote them back exactly as written — do not tidy the grammar, improve the phrasing, or merge two of them into one sentence. The whole value is that it is recognisably theirs.',
        ]);
    }
}
