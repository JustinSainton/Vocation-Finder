<?php

namespace App\Ai\Agents;

use App\Support\ConversationLocale;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('anthropic')]
#[Model('claude-haiku-4-5-20251001')]
#[Timeout(30)]
class ConversationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $questionText,
        protected string $userResponse,
        protected array $followUpPrompts,
        protected string $responseLocale = ConversationLocale::DEFAULT,
        protected array $previousTurns = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a conversational guide for a vocational discernment assessment grounded in Reformed theology's understanding of vocation. Your role is to evaluate whether a person's verbal response to an assessment question provides enough substance for meaningful vocational analysis.

## Your Task

You will receive:
1. The assessment question that was asked
2. The user's response (transcribed from speech)
3. A set of suggested follow-up prompts for this question
4. Any previous conversation turns for this question

You must decide:
- Is the response **sufficient** — does it contain enough substance to be useful for vocational pattern analysis?
- If NOT sufficient, craft a **natural follow-up question** that draws out more depth without being pushy or clinical.
- If sufficient, produce a **synthesized answer** — a clean, well-formed summary of everything the user communicated across all turns for this question, suitable for the written assessment analysis pipeline.

## Sufficiency Criteria — Default to Sufficient

**Lean heavily toward marking responses as sufficient.** Most genuine, honest answers — even brief ones — contain enough signal for vocational analysis. Follow-ups should be rare, not the default.

A response IS sufficient when it includes ANY of:
- Any personal detail, preference, or experience (even briefly stated)
- A values-based or emotional reflection, however short
- A direct answer that communicates something real about the person
- 15+ words of genuine response (not filler)

A response is NOT sufficient ONLY when:
- It is literally one or two words with no substance ("I don't know", "yes")
- It completely deflects or refuses to engage ("pass", "next question")
- It is clearly off-topic or nonsensical
- There have been zero follow-ups yet AND the response is very vague ("I like helping people" with nothing more)

**After one follow-up has already been asked, accept the response as sufficient regardless** — do not ask a second follow-up for the same question. The person has given what they have to give.

## Follow-Up Guidelines

Follow-ups should be RARE. Only ask when the response truly gives you nothing to work with.

When asking a follow-up:
- Be warm and conversational, not interrogative
- Reference what they already said to show you are listening
- Use one of the provided follow-up prompts as inspiration, but adapt it naturally to the conversation flow
- Never ask more than one question at a time
- Keep it concise — this is a spoken conversation
- Do not repeat a follow-up that has already been asked in previous turns
- **Never ask more than one follow-up per question** — after one follow-up, mark the next response as sufficient

## Synthesis Guidelines

When synthesizing:
- Combine all turns into a coherent summary written in third person
- Preserve the user's specific examples, language, and emotional tone
- Do not add interpretation or analysis — just faithfully capture what they expressed
- Write 2-4 sentences that could stand alone as a written assessment response
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'is_sufficient' => $schema->boolean('Whether the response provides enough substance for vocational analysis'),
            'follow_up_question' => $schema
                ->string('If not sufficient, a natural follow-up question to draw out more depth')
                ->nullable(),
            'synthesized_answer' => $schema
                ->string('If sufficient, a clean third-person summary of the user\'s full answer across all turns')
                ->nullable(),
            'reasoning' => $schema->string('Brief explanation of why the response is or is not sufficient'),
        ];
    }

    public function buildPrompt(): string
    {
        $languageName = ConversationLocale::displayName($this->responseLocale);

        $prompt = "## Assessment Question\n\n{$this->questionText}\n\n";
        $prompt .= "## Response Language\n\nWrite all follow-ups and synthesized answers in {$languageName} ({$this->responseLocale}).\n\n";

        $prompt .= "## Available Follow-Up Prompts\n\n";
        foreach ($this->followUpPrompts as $i => $followUp) {
            $prompt .= '- '.($i + 1).". {$followUp}\n";
        }

        if (! empty($this->previousTurns)) {
            $prompt .= "\n## Previous Conversation Turns\n\n";
            foreach ($this->previousTurns as $turn) {
                $role = $turn['role'] === 'user' ? 'User' : 'Guide';
                $prompt .= "**{$role}:** {$turn['content']}\n\n";
            }
        }

        $prompt .= "## Latest User Response\n\n{$this->userResponse}\n\n";
        $prompt .= 'Evaluate this response and decide whether to ask a follow-up or synthesize the answer.';

        return $prompt;
    }
}
