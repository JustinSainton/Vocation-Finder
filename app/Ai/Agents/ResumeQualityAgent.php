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

    /**
     * The four band scores are the only numbers the model is asked for.
     *
     * Totals and pass/fail are arithmetic, and a model asked for arithmetic
     * will get it wrong: llama3.2:3b returned a total of 40 against bands
     * summing to 45, and set the gate to true at the same time. Derived values
     * belong in {@see static::normalize()}, not in the schema.
     */
    public function schema(JsonSchema $schema): array
    {
        $band = fn (string $description) => $schema
            ->number()
            ->min(0)
            ->max(25)
            ->description($description)
            ->required();

        $list = fn (string $description) => $schema
            ->array()
            ->items($schema->string())
            ->description($description)
            ->required();

        return [
            'specificity_score' => $band('Specificity score 0-25'),
            'authenticity_score' => $band('Authenticity score 0-25'),
            'ats_score' => $band('ATS-friendliness score 0-25'),
            'alignment_score' => $band('Vocational alignment score 0-25'),
            'issues' => $list('Specific issues found. Empty array if none.'),
            'suggestions' => $list('Improvement suggestions. Empty array if none.'),
        ];
    }

    /**
     * The score at or above which a resume passes the quality gate.
     */
    public const QUALITY_GATE = 70;

    /**
     * The four scored bands, in report order.
     */
    public const BANDS = [
        'specificity_score',
        'authenticity_score',
        'ats_score',
        'alignment_score',
    ];

    /**
     * Add the derived fields to a raw agent result.
     *
     * Each band is clamped to its documented 0-25 range before summing, so a
     * provider that does not honour the schema bounds cannot push the total
     * out of range. Callers should read `total_score` and
     * `passes_quality_gate` from here rather than from the model.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function normalize(array $result): array
    {
        $total = 0.0;

        foreach (static::BANDS as $band) {
            $score = max(0.0, min(25.0, (float) ($result[$band] ?? 0)));

            $result[$band] = $score;
            $total += $score;
        }

        $result['total_score'] = $total;
        $result['passes_quality_gate'] = $total >= static::QUALITY_GATE;

        return $result;
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
