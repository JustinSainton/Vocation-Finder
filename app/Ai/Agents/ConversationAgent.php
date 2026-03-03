<?php

namespace App\Ai\Agents;

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
- Is the response **sufficient** — does it contain enough concrete detail, personal reflection, or specific examples to be useful for vocational pattern analysis?
- If NOT sufficient, craft a **natural follow-up question** that draws out more depth without being pushy or clinical.
- If sufficient, produce a **synthesized answer** — a clean, well-formed summary of everything the user communicated across all turns for this question, suitable for the written assessment analysis pipeline.

## Sufficiency Criteria

A response IS sufficient when it includes at least one of:
- A specific example, story, or experience
- A concrete preference with some reasoning behind it
- An emotional or values-based reflection that reveals motivation
- Enough detail to distinguish this person's perspective from a generic answer

A response is NOT sufficient when:
- It is vague, one-word, or purely abstract ("I like helping people")
- It repeats the question back without adding substance
- It deflects or gives a non-answer
- It is too brief to reveal any pattern (typically under 15 words of substance)

## Follow-Up Guidelines

When asking a follow-up:
- Be warm and conversational, not interrogative
- Reference what they already said to show you are listening
- Use one of the provided follow-up prompts as inspiration, but adapt it naturally to the conversation flow
- Never ask more than one question at a time
- Keep it concise — this is a spoken conversation
- Do not repeat a follow-up that has already been asked in previous turns

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
            'follow_up_question' => $schema->nullable(
                $schema->string('If not sufficient, a natural follow-up question to draw out more depth'),
            ),
            'synthesized_answer' => $schema->nullable(
                $schema->string('If sufficient, a clean third-person summary of the user\'s full answer across all turns'),
            ),
            'reasoning' => $schema->string('Brief explanation of why the response is or is not sufficient'),
        ];
    }

    public function buildPrompt(): string
    {
        $prompt = "## Assessment Question\n\n{$this->questionText}\n\n";

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
        $prompt .= "Evaluate this response and decide whether to ask a follow-up or synthesize the answer.";

        return $prompt;
    }
}
