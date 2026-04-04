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
class ResumeParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $resumeText,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a resume parser. Given raw text extracted from a resume PDF, extract structured data in JSON Resume format.

Rules:
- Extract what you can find. Don't fabricate data that isn't in the text.
- Dates should be in YYYY-MM or YYYY format when possible.
- Skills should be individual items, not categories.
- If a section is empty or not found, return an empty array.
- For LinkedIn PDF exports, the format typically has the name at top, then sections like Experience, Education, Skills, etc.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work' => $schema->array('Work experience entries', items: [
                'company' => $schema->string('Company name'),
                'position' => $schema->string('Job title'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date or empty if current'),
                'summary' => $schema->string('Role description or key responsibilities'),
            ]),
            'education' => $schema->array('Education entries', items: [
                'institution' => $schema->string('School name'),
                'area' => $schema->string('Field of study'),
                'studyType' => $schema->string('Degree type'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date'),
            ]),
            'skills' => $schema->array('Skills', items: [
                'name' => $schema->string('Skill name'),
                'level' => $schema->string('Proficiency level if stated'),
            ]),
            'certifications' => $schema->array('Certifications', items: $schema->string()),
            'volunteer' => $schema->array('Volunteer experience', items: [
                'organization' => $schema->string('Organization name'),
                'position' => $schema->string('Role'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date'),
                'summary' => $schema->string('Description'),
            ]),
        ];
    }

    public function buildPrompt(): string
    {
        return <<<PROMPT
Parse the following resume text into structured data:

{$this->resumeText}
PROMPT;
    }
}
