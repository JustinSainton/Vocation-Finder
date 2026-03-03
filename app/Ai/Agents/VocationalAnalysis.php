<?php

namespace App\Ai\Agents;

use App\Models\Assessment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-20250514')]
#[Timeout(120)]
class VocationalAnalysis implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected Assessment $assessment,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a vocational discernment analyst grounded in Reformed theology's understanding of vocation. Your task is to analyze assessment responses and identify patterns across six dimensions of vocational calling.

## Theological Framework

All legitimate work is ministry. Vocation is not limited to church work — it encompasses every sphere of human activity where God's people serve their neighbors and steward creation. Your analysis should reflect this integrated understanding:

- The Cultural Mandate: humans are called to cultivate and develop creation across all domains
- Common Grace: God distributes gifts broadly, and all honest work has dignity and purpose
- The Priesthood of All Believers: every Christian's vocation is a form of ministry
- Providence: circumstances, limitations, and opportunities can be means of divine guidance

## Analysis Dimensions

For each response, identify patterns in these six dimensions:

1. **Service Orientation** — How they naturally serve others (direct care vs. systemic solutions, people-focused vs. problem-focused, relational vs. technical, individual vs. community)

2. **Problem-Solving Draw** — What disorder compels them (injustice, inefficiency, suffering, absence of beauty, ignorance, environmental degradation). This reveals the DOMAIN of calling.

3. **Energy Sources** — Where gifts actually lie based on flow states and engagement (people/systems/ideas/things, creating/organizing/discovering/caring/teaching, solo vs. collaborative, intellectual vs. hands-on). This reveals the METHOD of work.

4. **Values & Decision-Making** — How they weigh competing goods (duty vs. calling, risk vs. security, family responsibility, theological maturity in thinking about calling)

5. **Response to Obstacles** — How they interpret limitations and closed doors (Providence thinking vs. pure obstacle-overcoming, adaptability vs. perseverance)

6. **Vision & Legacy** — Scope of desired impact (local vs. broad, immediate vs. generational, individuals vs. systems vs. culture vs. ideas)

## Category Mapping

Map patterns to these 17 vocational categories, scoring relevance 0-100:

1. Healing & Care
2. Teaching & Formation
3. Leadership & Management
4. Law & Policy
5. Protecting & Defending
6. Creating & Building
7. Maintaining & Repairing
8. Arts & Beauty
9. Discovering & Innovating
10. Nourishing & Hospitality
11. Commerce & Enterprise
12. Finance & Economics
13. Communication & Media
14. Advocating & Supporting
15. Knowledge & Information
16. Administration & Systems
17. Pastoral & Missionary Work

## Critical Rules

- Identify multi-dimensional callings (e.g., "an architect called to lead" not just "architecture")
- Identify the PRIMARY DOMAIN, MODE OF WORK, and SECONDARY ORIENTATION
- Ground every observation in the respondent's actual words — do not infer beyond what they expressed
- Be specific and personal, not generic
- Never use clinical or diagnostic language
- Treat the respondent with dignity and seriousness regardless of age
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dimensions' => $schema->object([
                'service_orientation' => $schema->object([
                    'pattern' => $schema->string('Primary service pattern identified'),
                    'mode' => $schema->enum(['direct_care', 'systemic', 'relational', 'technical', 'creative', 'educational']),
                    'evidence' => $schema->array($schema->string(), 'Specific quotes or references from responses'),
                ]),
                'problem_solving_draw' => $schema->object([
                    'primary_concern' => $schema->string('What type of disorder or need compels them'),
                    'scale' => $schema->enum(['individual', 'organizational', 'community', 'societal']),
                    'approach' => $schema->enum(['direct_service', 'policy', 'innovation', 'education', 'creation', 'care']),
                    'evidence' => $schema->array($schema->string()),
                ]),
                'energy_sources' => $schema->object([
                    'flow_activities' => $schema->string('Activities that produce flow states'),
                    'works_with' => $schema->enum(['people', 'systems', 'ideas', 'tangible_things']),
                    'mode' => $schema->enum(['creating', 'organizing', 'discovering', 'caring', 'teaching', 'leading']),
                    'collaboration' => $schema->enum(['solo', 'collaborative', 'leading_team']),
                    'evidence' => $schema->array($schema->string()),
                ]),
                'values_decision_making' => $schema->object([
                    'primary_driver' => $schema->string('What drives their decisions when values conflict'),
                    'risk_orientation' => $schema->enum(['risk_taking', 'security_seeking', 'calculated', 'faith_driven']),
                    'theological_maturity' => $schema->enum(['emerging', 'developing', 'mature']),
                    'evidence' => $schema->array($schema->string()),
                ]),
                'response_to_obstacles' => $schema->object([
                    'interpretation' => $schema->string('How they interpret limitations and setbacks'),
                    'providence_awareness' => $schema->enum(['strong', 'moderate', 'emerging', 'not_expressed']),
                    'resilience_style' => $schema->enum(['adaptive', 'persevering', 'reflective', 'resourceful']),
                    'evidence' => $schema->array($schema->string()),
                ]),
                'vision_legacy' => $schema->object([
                    'scope' => $schema->enum(['local', 'regional', 'broad', 'generational']),
                    'focus' => $schema->enum(['individuals', 'systems', 'culture', 'ideas', 'communities']),
                    'contribution_type' => $schema->string('What "making a difference" means to them'),
                    'evidence' => $schema->array($schema->string()),
                ]),
            ]),
            'category_scores' => $schema->array(
                $schema->object([
                    'category' => $schema->string('One of the 17 vocational category names'),
                    'score' => $schema->integer('Relevance score 0-100'),
                    'rationale' => $schema->string('Brief explanation for the score'),
                ]),
                'Scored relevance for each of the 17 vocational categories'
            ),
            'primary_domain' => $schema->string('The primary vocational domain (e.g., "designing and building structures that serve communities")'),
            'mode_of_work' => $schema->string('How they would work in that domain (e.g., "entrepreneurial ownership", "collaborative research")'),
            'secondary_orientation' => $schema->string('Secondary calling dimension (e.g., "leadership and team development")'),
            'ministry_connection' => $schema->string('How their vocation connects to ministry and service to neighbor — grounded in their specific responses'),
        ];
    }

    public function buildPrompt(): string
    {
        $answers = $this->assessment->answers()
            ->with('question.category')
            ->orderBy('id')
            ->get();

        $formatted = $answers->map(function ($answer) {
            $category = $answer->question->category->name ?? 'Unknown';
            $question = $answer->question->question_text;
            $response = $answer->response_text ?: $answer->audio_transcript;

            return "**[{$category}] Q: {$question}**\nResponse: {$response}";
        })->join("\n\n---\n\n");

        return <<<PROMPT
Analyze the following vocational discernment assessment responses. The respondent answered 20 questions across 7 categories designed to reveal their vocational calling.

## Assessment Responses

{$formatted}

## Your Task

Analyze ALL responses holistically across the six dimensions. Identify the respondent's:
1. Primary vocational domain — what field or type of work they are drawn to
2. Mode of work — how they would operate within that domain
3. Secondary orientation — additional calling dimensions that enrich the primary
4. Ministry connection — how their specific vocation IS ministry

Score each of the 17 vocational categories based on evidence from the responses. Be specific and personal — reference their actual words and experiences. This person's calling is multi-dimensional; capture that complexity.
PROMPT;
    }
}
