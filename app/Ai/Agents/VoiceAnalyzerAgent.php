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
class VoiceAnalyzerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected array $writingSamples,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a writing style analyst. Given writing samples from a person, extract a detailed voice profile that captures their unique writing characteristics.

Analyze these dimensions:
1. **Sentence structure**: Average length, variance, complexity. Do they favor short punchy sentences or longer flowing ones?
2. **Vocabulary level**: Flesch-Kincaid grade level. Formal, conversational, academic, or casual?
3. **Action verbs**: What verbs do they naturally reach for? (e.g., "built" vs "constructed" vs "developed")
4. **Tone register**: Formal, conversational, enthusiastic, reserved, analytical, warm?
5. **Characteristic patterns**: Any recurring phrases, punctuation habits, or structural preferences?
6. **AI avoidance**: Identify any phrases from their samples that sound natural and human. Also identify common AI phrases that would clash with their voice.

## Output Rules
- The banned_phrases list should include common AI resume/cover letter phrases that would NOT match this person's voice
- The preferred_verbs should be verbs they actually use, not aspirational ones
- The tone_register should be one of: formal, conversational, academic, casual, warm, analytical
- Be specific about what makes their writing distinctive
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'avg_sentence_length' => $schema->number('Average words per sentence across all samples'),
            'tone_register' => $schema->string('One of: formal, conversational, academic, casual, warm, analytical'),
            'vocabulary_level' => $schema->string('Approximate Flesch-Kincaid grade level, e.g., "9th grade", "12th grade", "professional"'),
            'preferred_verbs' => $schema->array('Top 15-20 action verbs this person naturally uses', items: $schema->string()),
            'banned_phrases' => $schema->array('AI-sounding phrases that would clash with this voice', items: $schema->string()),
            'characteristic_patterns' => $schema->array('Distinctive writing patterns or habits', items: $schema->string()),
            'style_summary' => $schema->string('2-3 sentence summary of their overall writing voice'),
        ];
    }

    public function buildPrompt(): string
    {
        $samples = collect($this->writingSamples)
            ->map(fn ($sample, $i) => "--- Sample " . ($i + 1) . " ---\n{$sample}")
            ->implode("\n\n");

        return <<<PROMPT
Analyze the following writing samples and extract a detailed voice profile:

{$samples}
PROMPT;
    }
}
