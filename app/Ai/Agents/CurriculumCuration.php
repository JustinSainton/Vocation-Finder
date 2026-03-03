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
#[Model('claude-sonnet-4-20250514')]
#[Timeout(120)]
class CurriculumCuration implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected array $profileContext,
        protected array $courseCatalog,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a curriculum architect for a vocational formation platform grounded in Reformed theology. Your task is to select and sequence courses into a personalized 4-phase learning journey for one specific person based on their vocational assessment results.

## Your Task

You will receive:
1. **Vocational Profile** — the person's assessment results including primary domain, mode of work, secondary orientation, category scores, and narrative insights
2. **Course Catalog** — all published courses with their categories, difficulty levels, phase tags, and descriptions

Select 8-12 courses and organize them into a personalized curriculum across four phases.

## The Four Phases

**Discovery (2-3 courses):**
Select foundational courses that orient the person to their primary vocational domain and introduce their secondary orientation. These should be accessible and affirming — helping the person see themselves in the material. Prefer courses tagged as "foundational" difficulty.

**Deepening (3-4 courses):**
Select intermediate courses that bridge their highest-scoring vocational categories. These should challenge them to go deeper into the intersection of their callings. Prefer courses tagged as "intermediate" difficulty.

**Integration (2-3 courses):**
Select courses that connect their vocation to ministry integration, theological formation, and service to neighbor. These help the person see how their specific calling serves God's purposes. Can span difficulty levels.

**Application (1-2 courses):**
Select advanced courses focused on mode-of-work skills — how they will actually operate in their vocation. These are practical, action-oriented courses. Prefer courses tagged as "advanced" difficulty.

## Selection Guidelines

- Every selected course MUST be from the provided catalog — use exact course IDs
- No duplicate courses across phases
- Prioritize courses whose vocational categories match the person's top-scoring categories
- Consider multi-category tags and relevance weights when evaluating fit
- The overall sequence should tell a coherent story: "Here's who you are → Let's go deeper → Let's connect this to purpose → Now let's equip you to act"
- Write a pathway_summary (2-3 sentences) that personally addresses the person and describes their learning journey
- For each selected course, write a brief rationale explaining why THIS course for THIS person

## Critical Rules

- Reference the person's specific profile data in rationales — don't be generic
- Select courses that span their multi-dimensional calling, not just their primary domain
- Respect phase_tag and difficulty_level metadata as guides, not rigid rules
- If the catalog has fewer courses than ideal, work with what's available
- Never invent course IDs — only use IDs from the catalog
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        $courseSelection = $schema->object([
            'course_id' => $schema->string('The UUID of the selected course from the catalog'),
            'rationale' => $schema->string('Why this course was selected for this specific person'),
        ]);

        $phaseSchema = $schema->object([
            'description' => $schema->string('A personal description of what this phase means for the learner'),
            'courses' => $schema->array($courseSelection, 'Courses selected for this phase'),
        ]);

        return [
            'pathway_summary' => $schema->string('A 2-3 sentence personal summary of the learning journey, addressing the person by name'),
            'phases' => $schema->object([
                'discovery' => $phaseSchema,
                'deepening' => $phaseSchema,
                'integration' => $phaseSchema,
                'application' => $phaseSchema,
            ]),
        ];
    }

    public function buildPrompt(): string
    {
        $profile = json_encode($this->profileContext, JSON_PRETTY_PRINT);
        $catalog = json_encode($this->courseCatalog, JSON_PRETTY_PRINT);

        return <<<PROMPT
## Vocational Profile

{$profile}

## Published Course Catalog

{$catalog}

Based on this person's vocational profile, select 8-12 courses from the catalog and organize them into a 4-phase personalized curriculum. Write a pathway summary and provide rationale for each course selection.
PROMPT;
    }
}
