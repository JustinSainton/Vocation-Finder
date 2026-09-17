<?php

namespace App\Ai\Agents;

use App\Support\TaxonomyPrompt;
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
class JobClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $jobTitle,
        protected string $jobDescription,
    ) {}

    public function instructions(): Stringable|string
    {
        $categories = "## Vocational Categories\n".TaxonomyPrompt::index();

        return <<<INSTRUCTIONS
You are a job classification specialist. Given a job title and description, classify the job into:

1. An O*NET SOC major group code (the first part, e.g., "29-0000" for Healthcare)
2. One or more vocational categories from the provided list, with relevance scores (0.0-1.0)

## SOC Major Groups
- 11-0000: Management
- 13-0000: Business & Financial Operations
- 15-0000: Computer & Mathematical
- 17-0000: Architecture & Engineering
- 19-0000: Life, Physical & Social Science
- 21-0000: Community & Social Service
- 23-0000: Legal
- 25-0000: Education, Training & Library
- 27-0000: Arts, Design, Entertainment, Sports & Media
- 29-0000: Healthcare Practitioners & Technical
- 31-0000: Healthcare Support
- 33-0000: Protective Service
- 35-0000: Food Preparation & Serving
- 37-0000: Building & Grounds Cleaning & Maintenance
- 39-0000: Personal Care & Service
- 41-0000: Sales & Related
- 43-0000: Office & Administrative Support
- 45-0000: Farming, Fishing & Forestry
- 47-0000: Construction & Extraction
- 49-0000: Installation, Maintenance & Repair
- 51-0000: Production
- 53-0000: Transportation & Material Moving
- 55-0000: Military Specific

{$categories}

## Rules
- Assign 1-3 vocational categories per job (most jobs fit 1-2 primary categories)
- Relevance scores should reflect how strongly the job aligns: 0.9+ = core match, 0.7-0.89 = strong, 0.5-0.69 = moderate
- For ministry/church roles, always include pastoral-missionary regardless of other categories
- A nurse manager should get both healing-care and leadership-management
INSTRUCTIONS;
    }

    /**
     * Relevance is bounded 0.0-1.0 in the schema itself so a provider that
     * constrains decoding against it cannot emit an out-of-range score.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'soc_code' => $schema
                ->string()
                ->description('The O*NET SOC major group code (e.g., "29-0000")')
                ->required(),
            'categories' => $schema
                ->array()
                ->items($schema->object([
                    'slug' => $schema
                        ->string()
                        ->enum(TaxonomyPrompt::slugs())
                        ->description('Category slug from the list above')
                        ->required(),
                    'relevance' => $schema
                        ->number()
                        ->min(0)
                        ->max(1)
                        ->description('Relevance score from 0.0 to 1.0')
                        ->required(),
                ]))
                ->description('Vocational category classifications. Empty array if none apply.')
                ->required(),
        ];
    }

    public function buildPrompt(): string
    {
        $description = mb_substr($this->jobDescription, 0, 1500);

        return <<<PROMPT
Classify this job listing:

**Title:** {$this->jobTitle}

**Description:**
{$description}
PROMPT;
    }
}
