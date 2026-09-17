<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\RunsOnTheConfiguredEngine;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Support\ConversationLocale;
use App\Support\SignalExtractor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Blueprint Layer 4: extract typed vocational signals from the narrative.
 *
 * This runs before taxonomy mapping, not after: Layer 4 produces the evidence
 * that Layer 5 maps. Every signal must carry the literal span of the answer it
 * came from, which {@see SignalExtractor} then verifies against
 * the source text — a quote the student never said is discarded rather than
 * stored.
 */
#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
#[Timeout(120)]
class SignalDetection implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, RunsOnTheConfiguredEngine;

    /**
     * @param  list<array{id: string, question: string, response: string}>  $answers
     */
    public function __construct(
        protected array $answers,
        protected string $responseLocale = ConversationLocale::DEFAULT,
    ) {}

    public function instructions(): Stringable|string
    {
        $types = collect(SignalType::cases())
            ->map(fn (SignalType $type) => "- {$type->value} — {$type->label()}")
            ->implode("\n");

        return <<<INSTRUCTIONS
        You extract structured vocational signals from what a person actually said.

        You are not interpreting their calling and you are not drawing conclusions.
        You are labelling evidence so a later stage can interpret it.

        ## Signal types

        {$types}

        These are not interchangeable and collapsing them is the failure this
        step exists to prevent. The distinction the blueprint draws:

        - Desire: "I love helping people understand things."
        - Skill: "People often ask me to explain things."
        - Burden: "It bothers me when people are confused and no one helps them."
        - Environment: "I like small groups more than big crowds."
        - Development need: "I start things but struggle to finish them."

        ## Aspiration versus demonstrated

        Every signal is tracked as one or the other, and the difference matters
        more than either alone.

        - **aspiration** — what they want, admire, or imagine.
        - **demonstrated** — what they have practised, endured, produced, or
          been trusted to carry.

        "I want to be a nurse" is aspiration. "I sat with her until she stopped
        crying" is demonstrated. Do not upgrade an aspiration because it is
        stated with feeling, and do not dismiss one because it is untested.

        ## The verbatim rule

        Every signal must include `verbatim`: a span copied **character for
        character** from the response it came from. Do not paraphrase it, do not
        tidy the grammar, do not join two separate phrases with an ellipsis, and
        do not translate it. If you cannot point at the words, do not emit the
        signal.

        `content` is your short labelling of what the span shows. `verbatim` is
        theirs.

        ## What to extract

        - Prefer fewer, well-evidenced signals over many thin ones.
        - A tension is a real contradiction between two things they said. Record
          it as a tension rather than averaging it away.
        - A constraint must state whether it reads as fixed, temporary, or
          negotiable. Constraints shape timing and sequence; they never define
          who someone is.
        - Distortions and immature expressions still count as evidence. Record
          what is there.
        - Do not invent a signal to fill a category. An absent signal is
          information.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'signals' => $schema
                ->array()
                ->items($schema->object([
                    'answer_id' => $schema
                        ->string()
                        ->description('The id of the answer this signal came from, exactly as given')
                        ->required(),
                    'type' => $schema
                        ->string()
                        ->enum(SignalType::values())
                        ->description('The signal category')
                        ->required(),
                    'track' => $schema
                        ->string()
                        ->enum(SignalTrack::values())
                        ->description('Whether this is something they want or something they have done')
                        ->required(),
                    'content' => $schema
                        ->string()
                        ->description('A short, plain label for what this span shows. One sentence.')
                        ->required(),
                    'verbatim' => $schema
                        ->string()
                        ->description('A span copied character for character from that answer')
                        ->required(),
                    'constraint_nature' => $schema
                        ->string()
                        ->enum(['fixed', 'temporary', 'negotiable', 'not_applicable'])
                        ->description('For constraints only; "not_applicable" for every other type')
                        ->required(),
                ]))
                ->description('Every signal found, in the order the answers were given')
                ->required(),
        ];
    }

    public function buildPrompt(): string
    {
        $locale = ConversationLocale::normalize($this->responseLocale);
        $languageName = ConversationLocale::displayName($locale);

        $formatted = collect($this->answers)
            ->map(fn (array $answer) => "### Answer id: {$answer['id']}\nQuestion: {$answer['question']}\nResponse: {$answer['response']}")
            ->implode("\n\n");

        return <<<PROMPT
        Extract the vocational signals from these responses.

        ## Response Language

        The responses are in {$languageName} ({$locale}). Write `content` in that
        language. Copy `verbatim` exactly as it appears, in the original language,
        without translating it.

        ## Responses

        {$formatted}

        Return every signal you can point at. Do not return one you cannot.
        PROMPT;
    }
}
