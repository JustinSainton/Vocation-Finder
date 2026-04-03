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
#[Timeout(20)]
class ResumeQualityAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $resumeText,
        protected string $jobTitle,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a resume quality reviewer focused on detecting AI-generated content and assessing authenticity. Score the resume on a 0-100 scale.

## Scoring Dimensions (each 0-25 points)

1. **Specificity (0-25)**: Does every bullet include concrete details? Numbers, dates, project names, tool names, team sizes? Or is it vague and generic?

2. **Authenticity (0-25)**: Does it read like a real person wrote it? Or does it sound like every other AI-generated resume? Check for: formulaic bullet patterns, excessive buzzwords, uniform sentence structure, performative enthusiasm.

3. **ATS-Friendliness (0-25)**: Standard section headings? Keywords from the target job? Clean formatting? No jargon without context?

4. **Vocational Alignment (0-25)**: Does the resume tell a coherent story about this person's calling? Or is it just a list of duties?

## Red Flags (each costs 5-10 points)
- Every bullet starts with the same pattern (e.g., all "Led..." or all "Managed...")
- Uses banned AI phrases: "leverage," "synergy," "passionate about," "proven track record," "results-driven"
- No concrete numbers or specifics in any bullet
- Reads at a 12th+ grade level when content doesn't warrant it
- Summary is generic enough to apply to anyone

## Output
Return total score (0-100), per-dimension scores, list of specific issues found, and improvement suggestions.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'total_score' => $schema->number('Overall quality score 0-100'),
            'specificity_score' => $schema->number('Specificity score 0-25'),
            'authenticity_score' => $schema->number('Authenticity score 0-25'),
            'ats_score' => $schema->number('ATS-friendliness score 0-25'),
            'alignment_score' => $schema->number('Vocational alignment score 0-25'),
            'issues' => $schema->array('Specific issues found', items: $schema->string()),
            'suggestions' => $schema->array('Improvement suggestions', items: $schema->string()),
            'passes_quality_gate' => $schema->boolean('True if total_score >= 70'),
        ];
    }

    public function buildPrompt(): string
    {
        return <<<PROMPT
Review this resume targeted at a "{$this->jobTitle}" role:

{$this->resumeText}

Score it on specificity, authenticity, ATS-friendliness, and vocational alignment.
PROMPT;
    }
}
