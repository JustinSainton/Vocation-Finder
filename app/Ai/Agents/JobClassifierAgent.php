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
class JobClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $jobTitle,
        protected string $jobDescription,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
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

## Vocational Categories
- healing-care: Healing & Care (healthcare, counseling, therapy, caregiving)
- teaching-formation: Teaching & Formation (education, training, mentorship)
- leadership-management: Leadership & Management (executive, administration, oversight)
- law-policy: Law & Policy (legal, regulatory, compliance, governance)
- protecting-defending: Protecting & Defending (military, law enforcement, security, emergency)
- creating-building: Creating & Building (construction, engineering, manufacturing, crafts)
- maintaining-repairing: Maintaining & Repairing (maintenance, repair, technical service)
- arts-beauty: Arts & Beauty (visual arts, design, performing arts, aesthetics)
- discovering-innovating: Discovering & Innovating (research, science, exploration, R&D)
- nourishing-hospitality: Nourishing & Hospitality (food service, hospitality, catering)
- commerce-enterprise: Commerce & Enterprise (sales, business development, entrepreneurship)
- finance-economics: Finance & Economics (accounting, banking, financial planning)
- communication-media: Communication & Media (journalism, PR, marketing, broadcasting)
- advocating-supporting: Advocating & Supporting (social work, community service, nonprofits)
- knowledge-information: Knowledge & Information (library science, data management, archives)
- administration-systems: Administration & Systems (IT, systems admin, office management)
- pastoral-missionary: Pastoral & Missionary Work (ministry, chaplaincy, missions, church leadership)

## Rules
- Assign 1-3 vocational categories per job (most jobs fit 1-2 primary categories)
- Relevance scores should reflect how strongly the job aligns: 0.9+ = core match, 0.7-0.89 = strong, 0.5-0.69 = moderate
- For ministry/church roles, always include pastoral-missionary regardless of other categories
- A nurse manager should get both healing-care and leadership-management
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'soc_code' => $schema->string('The O*NET SOC major group code (e.g., "29-0000")'),
            'categories' => $schema->array('Vocational category classifications', items: [
                'slug' => $schema->string('Category slug from the list above'),
                'relevance' => $schema->number('Relevance score from 0.0 to 1.0'),
            ]),
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
