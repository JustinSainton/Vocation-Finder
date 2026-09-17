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

    /**
     * Every field is required so the serialized schema carries a "required"
     * list. Providers that constrain decoding against the schema treat an
     * absent list as "all keys optional" and emit partial objects. A section
     * the resume does not contain is an empty array, never a missing key.
     */
    public function schema(JsonSchema $schema): array
    {
        $text = fn (string $description) => $schema
            ->string()
            ->description($description)
            ->required();

        return [
            'work' => $schema
                ->array()
                ->items($schema->object([
                    'company' => $text('Company name'),
                    'position' => $text('Job title'),
                    'startDate' => $text('Start date'),
                    'endDate' => $text('End date, or an empty string if this is the current role'),
                    'summary' => $text('Role description or key responsibilities'),
                ]))
                ->description('Work experience entries. Empty array if none are present.')
                ->required(),
            'education' => $schema
                ->array()
                ->items($schema->object([
                    'institution' => $text('School name'),
                    'area' => $text('Field of study'),
                    'studyType' => $text('Degree type'),
                    'startDate' => $text('Start date'),
                    'endDate' => $text('End date'),
                ]))
                ->description('Education entries. Empty array if none are present.')
                ->required(),
            'skills' => $schema
                ->array()
                ->items($schema->object([
                    'name' => $text('Skill name'),
                    'level' => $text('Proficiency level if stated, otherwise an empty string'),
                ]))
                ->description('Skills. Empty array if none are present.')
                ->required(),
            'certifications' => $schema
                ->array()
                ->items($schema->string())
                ->description('Certifications. Empty array if none are present.')
                ->required(),
            'volunteer' => $schema
                ->array()
                ->items($schema->object([
                    'organization' => $text('Organization name'),
                    'position' => $text('Role'),
                    'startDate' => $text('Start date'),
                    'endDate' => $text('End date'),
                    'summary' => $text('Description'),
                ]))
                ->description('Volunteer experience. Empty array if none are present.')
                ->required(),
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
