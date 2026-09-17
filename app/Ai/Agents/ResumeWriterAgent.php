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
#[Model('claude-sonnet-4-6')]
#[Timeout(60)]
class ResumeWriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected array $careerProfile,
        protected array $vocationalProfile,
        protected array $jobDescription,
        protected ?array $voiceProfile = null,
        protected string $lifeStage = 'experienced',
    ) {}

    public function instructions(): Stringable|string
    {
        $bannedList = implode(', ', $this->getBannedPhrases());

        return <<<INSTRUCTIONS
You are a resume writing expert who creates authentic, ATS-friendly resumes tailored to specific job opportunities. You write resumes that sound like a real person, not an AI.

## Core Principles

1. **Vocational alignment**: This person has a calling. Their resume should reflect not just what they can do, but why they do it. Use their vocational profile to emphasize experiences that align with their primary pathways.

2. **Company-specific tailoring**: Mirror keywords from the job description naturally. If the job says "patient care," don't say "healthcare delivery." Match their language.

3. **Anti-AI-slop rules**:
   - NEVER use these phrases: {$bannedList}
   - Vary sentence structure: no more than 2 consecutive bullets can start with the same grammatical pattern
   - Every bullet must include at least one concrete detail (number, name, tool, date)
   - Write at a 9th-grade reading level unless the voice profile indicates otherwise
   - Mix short and long sentences naturally

4. **Life stage awareness**: This person is at the "{$this->lifeStage}" stage. Adjust the resume format accordingly:
   - Middle/high school: skills-focused, education-first, include activities and volunteer work
   - College: education + relevant experience, include projects and coursework
   - Early career: hybrid skills + experience
   - Experienced: chronological, experience-first

5. **ATS format**: Single column, standard section headings (Professional Summary, Work Experience, Education, Skills), no tables or graphics.

## Output Format
Return structured JSON Resume data. Include only sections that have content — don't fabricate experiences.
INSTRUCTIONS;
    }

    /**
     * Every field is required so the serialized schema carries a "required"
     * list. Providers that constrain decoding against the schema treat an
     * absent list as "all keys optional" and emit partial objects. A section
     * with nothing to say is an empty array, never a missing key.
     */
    public function schema(JsonSchema $schema): array
    {
        $text = fn (string $description) => $schema
            ->string()
            ->description($description)
            ->required();

        $bullets = fn (string $description) => $schema
            ->array()
            ->items($schema->string())
            ->description($description)
            ->required();

        return [
            'summary' => $text('Professional summary or objective (2-3 sentences)'),
            'work' => $schema
                ->array()
                ->items($schema->object([
                    'company' => $text('Company name'),
                    'position' => $text('Job title'),
                    'startDate' => $text('Start date'),
                    'endDate' => $text('End date, or "Present" if this is the current role'),
                    'highlights' => $bullets('Achievement bullets'),
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
                    'highlights' => $bullets('Relevant achievements'),
                ]))
                ->description('Education entries. Empty array if none are present.')
                ->required(),
            'skills' => $schema
                ->array()
                ->items($schema->object([
                    'name' => $text('Skill category'),
                    'keywords' => $bullets('Individual skills'),
                ]))
                ->description('Skills grouped by category. Empty array if none are present.')
                ->required(),
            'volunteer' => $schema
                ->array()
                ->items($schema->object([
                    'organization' => $text('Organization name'),
                    'position' => $text('Role'),
                    'startDate' => $text('Start date'),
                    'endDate' => $text('End date'),
                    'highlights' => $bullets('Achievement bullets'),
                ]))
                ->description('Volunteer experience. Empty array if none are present.')
                ->required(),
        ];
    }

    public function buildPrompt(): string
    {
        $career = json_encode($this->careerProfile, JSON_PRETTY_PRINT);
        $vocation = json_encode($this->vocationalProfile, JSON_PRETTY_PRINT);
        $job = json_encode($this->jobDescription, JSON_PRETTY_PRINT);

        $voiceSection = '';
        if ($this->voiceProfile) {
            $voice = json_encode($this->voiceProfile, JSON_PRETTY_PRINT);
            $voiceSection = <<<VOICE

## Voice Profile (match this person's writing style)
{$voice}
VOICE;
        }

        return <<<PROMPT
Generate a resume for the following person, tailored to the specific job listing.

## Career History
{$career}

## Vocational Profile (their calling and strengths)
{$vocation}

## Target Job
{$job}
{$voiceSection}

Generate a complete, tailored resume. Prioritize and reorder experiences based on relevance to this specific job and alignment with their vocational pathways.
PROMPT;
    }

    private function getBannedPhrases(): array
    {
        $defaults = [
            'leverage', 'synergy', 'fast-paced environment', 'proven track record',
            'results-driven', 'passionate about', 'I\'m excited to apply',
            'team player', 'self-starter', 'think outside the box',
            'go-getter', 'detail-oriented professional', 'hit the ground running',
        ];

        return array_merge($defaults, $this->voiceProfile['banned_phrases'] ?? []);
    }
}
