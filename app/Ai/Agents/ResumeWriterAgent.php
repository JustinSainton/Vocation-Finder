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

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string('Professional summary or objective (2-3 sentences)'),
            'work' => $schema->array('Work experience entries', items: [
                'company' => $schema->string('Company name'),
                'position' => $schema->string('Job title'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date or "Present"'),
                'highlights' => $schema->array('Achievement bullets', items: $schema->string()),
            ]),
            'education' => $schema->array('Education entries', items: [
                'institution' => $schema->string('School name'),
                'area' => $schema->string('Field of study'),
                'studyType' => $schema->string('Degree type'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date'),
                'highlights' => $schema->array('Relevant achievements', items: $schema->string()),
            ]),
            'skills' => $schema->array('Skills grouped by category', items: [
                'name' => $schema->string('Skill category'),
                'keywords' => $schema->array('Individual skills', items: $schema->string()),
            ]),
            'volunteer' => $schema->array('Volunteer experience', items: [
                'organization' => $schema->string('Organization name'),
                'position' => $schema->string('Role'),
                'startDate' => $schema->string('Start date'),
                'endDate' => $schema->string('End date'),
                'highlights' => $schema->array('Achievement bullets', items: $schema->string()),
            ]),
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
