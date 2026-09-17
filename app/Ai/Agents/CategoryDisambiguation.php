<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\RunsOnTheConfiguredEngine;
use App\Support\TaxonomyPrompt;
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
 * Second interpretive pass. Runs only when the first pass leaves several
 * pathways genuinely competing.
 *
 * This agent receives the expanded taxonomy layer for the competing categories
 * and the differentiating questions the blueprint wrote for exactly this
 * moment. It re-reads the student's own words against those questions and
 * resolves the ranking, or declines to.
 */
#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
#[Timeout(120)]
class CategoryDisambiguation implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, RunsOnTheConfiguredEngine;

    /**
     * @param  array<int, string>  $competingSlugs
     * @param  array<int, array<string, mixed>>  $answers
     */
    public function __construct(
        protected array $competingSlugs,
        protected array $answers,
        protected bool $areAdjacent = false,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are resolving a genuine ambiguity in a vocational assessment. An earlier
pass scored several pathways closely enough that the result is currently a
guess. Your job is to determine which pathway the evidence actually supports.

## How to decide

Work from the differentiating questions supplied for each pathway. They were
written for precisely this comparison. Answer them against what the student
actually wrote, quoting their words.

Apply these rules, in this order:

1. **Object over activity.** When pathways compete, what the person is drawn
   *toward* matters more than what they were *doing*. Someone who organized a
   fundraiser for a sick friend is oriented toward the friend, not toward
   event logistics.
2. **Burdens outweigh enjoyment.** What a student cannot walk past is stronger
   evidence than what they enjoy. Enjoyment is easily shaped by circumstance;
   burden rarely is.
3. **Distortion is still evidence.** If the narrative matches a pathway's
   distorted expression rather than its healthy one, that pathway may still be
   correct. Note the immature expression. Never score it down for being immature.
4. **Multi-dimensional is a valid answer.** If the evidence genuinely supports
   more than one pathway rather than being unclear between them, say so. Do not
   manufacture a winner.

## When you cannot resolve it

If the student's responses do not contain enough evidence to separate the
pathways, say that plainly and set `resolved` to false. An honest "the evidence
does not yet distinguish these" is a correct answer and produces a testable next
step. A confident wrong answer does not.

Never state or imply that a pathway is the student's destiny, calling, or what
God has determined for them. You are interpreting evidence, not declaring fate.
INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Every field is required. Providers that constrain decoding against the
     * schema treat an absent "required" list as "all keys optional" and will
     * emit a partial object; the two conditional fields use an empty string
     * rather than absence so the key is always present.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resolved' => $schema
                ->boolean()
                ->description('True only if the evidence genuinely separates the competing pathways')
                ->required(),
            'ranking' => $schema
                ->array()
                ->items($schema->object([
                    /*
                     | Only the categories actually competing. The re-ranking
                     | is applied by matching these names against the analysis
                     | rows, so a name from outside the pair — or the right
                     | category under a different spelling — is a row that
                     | silently re-ranks nothing.
                     */
                    'category' => $schema->string()->enum($this->competingNames())->description('The vocational category name')->required(),
                    'score' => $schema->integer()->min(0)->max(100)->description('Relevance score 0-100 after re-ranking')->required(),
                    'rationale' => $schema->string()->description("Why, quoting the student's own words")->required(),
                ]))
                ->description('The competing categories, re-ranked')
                ->required(),
            'differentiator' => $schema
                ->string()
                ->description('The single distinction that decided it, in plain language')
                ->required(),
            'distortion_noted' => $schema
                ->string()
                ->description('Any immature expression observed. Empty string if none. Internal only — never surfaced raw.')
                ->required(),
            'insufficient_evidence_reason' => $schema
                ->string()
                ->description('When resolved is false, what evidence is missing. Empty string otherwise.')
                ->required(),
        ];
    }

    /**
     * The names this disambiguation may legitimately return.
     *
     * Falls back to the whole taxonomy if the competing slugs resolve to
     * nothing, because an empty enum is not a schema.
     *
     * @return list<string>
     */
    protected function competingNames(): array
    {
        $names = TaxonomyPrompt::namesFor($this->competingSlugs);

        return $names === [] ? TaxonomyPrompt::names() : $names;
    }

    public function buildPrompt(): string
    {
        $taxonomy = TaxonomyPrompt::disambiguation($this->competingSlugs);
        $responses = collect($this->answers)
            ->map(fn (array $answer) => "**Q: {$answer['question']}**\nResponse: {$answer['response']}")
            ->implode("\n\n");

        $adjacency = $this->areAdjacent
            ? 'These pathways are declared adjacent in the taxonomy — they are commonly '.
              'confused, and the differentiating questions below are the intended tool.'
            : 'These pathways are not declared adjacent. The narrative may be genuinely '.
              'multi-dimensional rather than ambiguous; weigh that possibility seriously.';

        return <<<PROMPT
        The following pathways scored closely enough to be indistinguishable.

        {$adjacency}

        ## The competing pathways

        {$taxonomy}

        ## The student's responses

        {$responses}

        Resolve the ranking using the differentiating questions above, quoting the
        student's own words as evidence. If the evidence does not separate them,
        set resolved to false and say what is missing.
        PROMPT;
    }
}
