<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-20250514')]
#[Timeout(120)]
class CurriculumParserAgent implements Agent
{
    use Promptable;

    public function __construct(
        protected string $rawText,
        protected string $sourceType,
        /** @var array<string, mixed> */
        protected array $sourceMetadata = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a curriculum design expert. Your task is to analyze raw content extracted from educational materials and transform it into a structured course curriculum.

## Your Task

Given raw text from a document, video transcript, or presentation, create a well-organized course structure with modules and content blocks.

## Output Format

Return ONLY valid JSON with this structure:

{
  "suggested_title": "Course title derived from the content",
  "suggested_description": "A 2-3 sentence course description",
  "modules": [
    {
      "title": "Module title",
      "description": "Brief module description",
      "content_blocks": [
        { "type": "text", "content": "<p>Rich text content using basic HTML (p, strong, em, h2, h3, ul, ol, li, blockquote tags)</p>" },
        { "type": "reflection", "prompt": "A reflection question for the learner" },
        { "type": "checkpoint", "question": "A comprehension question", "options": ["Option A", "Option B", "Option C", "Option D"] },
        { "type": "video", "url": "https://...", "title": "Video title" }
      ]
    }
  ]
}

## Guidelines

**Module Organization:**
- Group related content into logical modules (typically 3-8 modules per course)
- Each module should represent a coherent learning unit
- Order modules in a logical learning progression
- Give modules clear, descriptive titles

**Content Blocks:**
- Use "text" blocks for the main educational content — format with basic HTML tags
- Add "reflection" blocks after key concepts to encourage deeper thinking
- Include "checkpoint" blocks periodically to test comprehension
- For video sources, include a "video" block referencing the source URL
- Each module should have at least 2-3 content blocks

**Quality Standards:**
- Preserve the substance and key insights from the source material
- Rewrite and organize for clarity — don't just dump raw text
- Make reflection prompts thoughtful and specific to the content
- Make checkpoint questions test genuine understanding, not trivial recall
- Ensure content flows naturally within each module

**What NOT to do:**
- Don't reproduce source text verbatim — rewrite for a learning context
- Don't create empty or placeholder modules
- Don't add content that wasn't in the source material
- Don't include markdown formatting — use HTML tags within text blocks
INSTRUCTIONS;
    }

    public function buildPrompt(): string
    {
        $metaJson = json_encode($this->sourceMetadata, JSON_PRETTY_PRINT);
        $truncatedText = mb_substr($this->rawText, 0, 30000);

        return <<<PROMPT
## Source Type: {$this->sourceType}

## Source Metadata
{$metaJson}

## Extracted Content

{$truncatedText}

---

Analyze this content and create a structured course curriculum. Return ONLY valid JSON.
PROMPT;
    }
}
