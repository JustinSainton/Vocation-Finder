# Technical Research: Best Practices for AI-Powered Assessment Platform

**Project:** Vocation-Finder
**Date:** 2026-03-02
**Scope:** Laravel + Claude API + Expo React Native + Multi-Tenant SaaS

---

## Table of Contents

1. [Laravel + Claude/Anthropic API Integration Patterns](#1-laravel--claudeanthropic-api-integration-patterns)
2. [Queue Architecture for Long-Running AI Jobs](#2-queue-architecture-for-long-running-ai-jobs)
3. [Two-Phase AI Prompting: Structured Analysis then Narrative Synthesis](#3-two-phase-ai-prompting-structured-analysis-then-narrative-synthesis)
4. [Handling AI Output Validation](#4-handling-ai-output-validation)
5. [Real-Time Audio Conversation Architecture](#5-real-time-audio-conversation-architecture)
6. [Laravel Multi-Tenant SaaS Patterns](#6-laravel-multi-tenant-saas-patterns)
7. [Assessment Platform Architecture: Autosave, Progress, Resume](#7-assessment-platform-architecture-autosave-progress-resume)
8. [Offline-First Patterns for Assessment Tools](#8-offline-first-patterns-for-assessment-tools)
9. [AI Cost Optimization](#9-ai-cost-optimization)
10. [Psychometric Assessment Data Modeling](#10-psychometric-assessment-data-modeling)

---

## 1. Laravel + Claude/Anthropic API Integration Patterns

### Package Selection: Three Tiers

There are three viable approaches for Laravel + Claude integration, from highest to lowest abstraction:

#### Tier 1: Prism PHP (Recommended for this project)

Prism is the Laravel-native AI abstraction layer, officially featured on the Laravel blog. It provides a unified interface across providers (Anthropic, OpenAI, Ollama) with Laravel-idiomatic patterns.

**Installation:**
```bash
composer require prism-php/prism
```

**Structured Output Example (your vocational analysis):**
```php
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\BooleanSchema;

$vocationalAnalysisSchema = new ObjectSchema(
    name: 'vocational_analysis',
    description: 'Multi-dimensional vocational pattern analysis',
    properties: [
        new ObjectSchema(
            name: 'service_orientation',
            description: 'Service orientation patterns',
            properties: [
                new StringSchema('primary_mode', 'Direct care vs systemic solutions vs creative'),
                new StringSchema('focus', 'People-focused vs problem-focused'),
                new StringSchema('scale', 'Individual vs community vs societal'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['primary_mode', 'focus', 'scale', 'confidence']
        ),
        new ObjectSchema(
            name: 'problem_solving',
            description: 'What disorder compels them',
            properties: [
                new StringSchema('compulsion_type', 'injustice|inefficiency|suffering|absence_of_beauty|ignorance|other'),
                new StringSchema('problem_scale', 'individual|organizational|societal'),
                new StringSchema('approach', 'direct_service|policy|innovation|education'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['compulsion_type', 'problem_scale', 'approach', 'confidence']
        ),
        new ObjectSchema(
            name: 'energy_sources',
            description: 'Where their gifts and energy lie',
            properties: [
                new StringSchema('works_with', 'people|systems|ideas|tangible_things'),
                new StringSchema('activity_mode', 'creating|organizing|discovering|caring|teaching'),
                new StringSchema('collaboration_preference', 'solo|collaborative|leading'),
                new StringSchema('engagement_type', 'intellectual|hands_on|relational'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['works_with', 'activity_mode', 'collaboration_preference', 'engagement_type', 'confidence']
        ),
        new ObjectSchema(
            name: 'values_decision_making',
            description: 'How they weigh competing goods',
            properties: [
                new StringSchema('primary_driver', 'duty|calling|risk_tolerance|security'),
                new StringSchema('family_weight', 'How family/community responsibility factors in'),
                new StringSchema('theological_maturity', 'emerging|developing|mature'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['primary_driver', 'family_weight', 'theological_maturity', 'confidence']
        ),
        new ObjectSchema(
            name: 'obstacle_response',
            description: 'How they interpret closed doors and limitations',
            properties: [
                new StringSchema('interpretation_style', 'providence|obstacle_overcoming|mixed'),
                new StringSchema('adaptability', 'highly_adaptable|moderately_adaptable|persistent_through'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['interpretation_style', 'adaptability', 'confidence']
        ),
        new ObjectSchema(
            name: 'vision_legacy',
            description: 'Scope and nature of desired impact',
            properties: [
                new StringSchema('impact_scope', 'local|regional|national|global'),
                new StringSchema('impact_timeline', 'immediate|generational'),
                new StringSchema('contribution_focus', 'individuals|systems|culture|ideas|communities'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
            ],
            requiredFields: ['impact_scope', 'impact_timeline', 'contribution_focus', 'confidence']
        ),
        new ArraySchema(
            name: 'category_rankings',
            description: 'Top 5 vocational categories ranked by fit',
            items: new ObjectSchema(
                name: 'category_rank',
                description: 'A ranked vocational category',
                properties: [
                    new StringSchema('category', 'One of the 17 vocational categories'),
                    new NumberSchema('score', 'Fit score 0-100'),
                    new StringSchema('evidence', 'Key evidence from responses'),
                ],
                requiredFields: ['category', 'score', 'evidence']
            )
        ),
        new ObjectSchema(
            name: 'dimensional_mapping',
            description: 'The multi-dimensional vocational mapping',
            properties: [
                new StringSchema('primary_domain', 'The primary vocational domain'),
                new StringSchema('mode_of_work', 'How they will work in that domain'),
                new StringSchema('secondary_orientation', 'Supporting vocational orientation'),
            ],
            requiredFields: ['primary_domain', 'mode_of_work', 'secondary_orientation']
        ),
    ],
    requiredFields: [
        'service_orientation', 'problem_solving', 'energy_sources',
        'values_decision_making', 'obstacle_response', 'vision_legacy',
        'category_rankings', 'dimensional_mapping'
    ]
);

$response = Prism::structured()
    ->using(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
    ->withSchema($vocationalAnalysisSchema)
    ->withSystemPrompt($systemPrompt)
    ->withPrompt($formattedResponses)
    ->withProviderOptions(['use_tool_calling' => true]) // More reliable for complex schemas
    ->asStructured();

$analysis = $response->structured; // PHP associative array
```

**Tool Use Example (follow-up question generation for conversation mode):**
```php
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tool;

$followUpTool = Tool::as('generate_follow_up')
    ->for('Generates a follow-up question based on response depth analysis')
    ->withStringParameter('assessment', 'Whether the response is sufficient or needs probing')
    ->withStringParameter('follow_up_question', 'The follow-up question if needed')
    ->withBooleanParameter('advance_to_next', 'Whether to move to the next question')
    ->using(function (string $assessment, string $follow_up_question, bool $advance_to_next) {
        return [
            'assessment' => $assessment,
            'follow_up_question' => $follow_up_question,
            'advance_to_next' => $advance_to_next,
        ];
    });

$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
    ->withTools([$followUpTool])
    ->withMaxSteps(1)
    ->withSystemPrompt('You are a vocational discernment counselor...')
    ->withPrompt("Question: {$question}\nResponse: {$userResponse}")
    ->asText();
```

#### Tier 2: Golden Path Digital Laravel-Claude

A thinner wrapper around the official Anthropic PHP SDK with Laravel-specific niceties.

```bash
composer require goldenpathdigital/laravel-claude
```

```php
use GoldenPathDigital\Claude\Facades\Claude;

$response = Claude::messages()->create([
    'model' => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 4096,
    'system' => $systemPrompt,
    'messages' => [
        ['role' => 'user', 'content' => $formattedResponses],
    ],
    'output_config' => [
        'format' => [
            'type' => 'json_schema',
            'schema' => $jsonSchema,
        ],
    ],
]);
```

#### Tier 3: Direct HTTP Client (Most Control)

For maximum control, use Laravel's HTTP client directly against the Anthropic API.

```php
use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'x-api-key' => config('services.anthropic.key'),
    'anthropic-version' => '2023-06-01',
    'content-type' => 'application/json',
])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 4096,
    'system' => [
        [
            'type' => 'text',
            'text' => $systemPrompt,
            'cache_control' => ['type' => 'ephemeral'], // Enable prompt caching
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => $formattedResponses],
    ],
    'output_config' => [
        'format' => [
            'type' => 'json_schema',
            'schema' => $jsonSchema,
        ],
    ],
]);
```

### Structured Outputs: The New Standard (GA as of 2025)

Structured outputs are now generally available (no longer beta) on Claude Opus 4.6, Sonnet 4.6, Sonnet 4.5, Opus 4.5, and Haiku 4.5. The key parameter has moved from `output_format` to `output_config.format`, and beta headers are no longer required.

**How it works:** Constrained decoding compiles your JSON schema into a grammar and actively restricts token generation during inference. The model literally cannot produce tokens that would violate your schema.

**Supported JSON Schema features:**
- `type`: string, number, integer, boolean, array, object, null
- `enum` for string and integer types
- `properties`, `required`, `additionalProperties` for objects
- `items` for arrays
- `anyOf` for union types
- `$ref` and `$defs` for schema composition
- Recursive schemas for tree/graph structures

**Limitations:**
- `additionalProperties` must be `false` on all objects
- All object properties should be listed in `required`
- No `pattern`, `format`, `minimum`, `maximum`, `minItems`, `maxItems`
- Top-level schema must be an `object` type

### Streaming Responses in Laravel

Laravel 12 provides first-class streaming support with generators and SSE:

```php
// Server-Sent Events for real-time analysis progress
Route::get('/assessments/{id}/analysis-stream', function (Assessment $assessment) {
    return response()->eventStream(function () use ($assessment) {
        // Phase 1: Pattern Analysis
        yield new StreamedEvent(
            event: 'phase',
            data: json_encode(['phase' => 'pattern_analysis', 'status' => 'started']),
        );

        $analysisStream = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->withOptions(['stream' => true])
          ->post('https://api.anthropic.com/v1/messages', [
              'model' => 'claude-sonnet-4-5-20250929',
              'max_tokens' => 4096,
              'stream' => true,
              'messages' => $messages,
          ]);

        foreach ($analysisStream as $chunk) {
            yield new StreamedEvent(
                event: 'token',
                data: $chunk,
            );
        }

        yield new StreamedEvent(
            event: 'complete',
            data: json_encode(['status' => 'done']),
        );
    });
});
```

### Recommendation for Vocation-Finder

Use **Prism PHP as the primary interface** for most AI interactions (structured analysis, tool use, follow-up generation), and fall back to **direct HTTP client** for advanced features like prompt caching with `cache_control` blocks, streaming, and the Batch API. This gives you the best of both worlds: Laravel-idiomatic code for 90% of cases and full API control when needed.

---

## 2. Queue Architecture for Long-Running AI Jobs

### The Problem

Your AI analysis pipeline involves:
1. Sending 20 open-ended responses to Claude (large input)
2. Phase A: Structured pattern analysis (~15-30 seconds)
3. Phase B: Narrative synthesis (~15-30 seconds)
4. Total wall time: 30-60 seconds per assessment

This exceeds typical web request timeouts and must run asynchronously.

### Job Architecture

```php
// app/Jobs/AnalyzeAssessmentJob.php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\ClaudeAnalysisService;
use App\Events\AnalysisCompleted;
use App\Events\AnalysisFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AnalyzeAssessmentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Unique lock: prevent duplicate analysis for same assessment.
     * Lock expires after 5 minutes (safety valve).
     */
    public int $uniqueFor = 300;

    /**
     * Maximum attempts before permanent failure.
     * AI API calls can have transient failures.
     */
    public int $tries = 3;

    /**
     * Timeout: 120 seconds per attempt.
     * Claude analysis typically takes 30-60s.
     * This gives 2x headroom.
     */
    public int $timeout = 120;

    /**
     * Mark as failed on timeout (do not silently retry).
     */
    public bool $failOnTimeout = true;

    /**
     * Exponential backoff between retries.
     * Wait 10s, then 30s, then 60s.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public Assessment $assessment,
        public string $idempotencyKey,
    ) {}

    /**
     * Unique ID prevents duplicate jobs for same assessment.
     */
    public function uniqueId(): string
    {
        return $this->assessment->id;
    }

    /**
     * Prevent overlapping analysis for the same user.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->assessment->user_id),
        ];
    }

    public function handle(ClaudeAnalysisService $claude): void
    {
        // Idempotency: skip if already analyzed
        if ($this->assessment->vocationalProfile()->exists()) {
            logger()->info('Assessment already analyzed, skipping.', [
                'assessment_id' => $this->assessment->id,
                'idempotency_key' => $this->idempotencyKey,
            ]);
            return;
        }

        // Update status
        $this->assessment->update(['status' => 'analyzing']);

        // Phase A: Structured pattern analysis
        $structuredAnalysis = $claude->analyzePatterns($this->assessment);

        // Phase B: Narrative synthesis
        $narrativeProfile = $claude->synthesizeNarrative(
            $this->assessment,
            $structuredAnalysis
        );

        // Save results
        $this->assessment->vocationalProfile()->create([
            'opening_synthesis' => $narrativeProfile['opening_synthesis'],
            'vocational_orientation' => $narrativeProfile['vocational_orientation'],
            'primary_pathways' => $narrativeProfile['primary_pathways'],
            'specific_considerations' => $narrativeProfile['specific_considerations'],
            'next_steps' => $narrativeProfile['next_steps'],
            'ministry_integration' => $narrativeProfile['ministry_integration'],
            'ai_analysis_raw' => $structuredAnalysis,
            'dimensional_mapping' => $structuredAnalysis['dimensional_mapping'],
            'category_scores' => $structuredAnalysis['category_rankings'],
        ]);

        $this->assessment->update(['status' => 'completed']);

        event(new AnalysisCompleted($this->assessment));
    }

    /**
     * Handle permanent failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->assessment->update(['status' => 'failed']);

        event(new AnalysisFailed($this->assessment, $exception->getMessage()));

        logger()->error('Assessment analysis permanently failed.', [
            'assessment_id' => $this->assessment->id,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Determine if the job should retry based on exception type.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}
```

### Queue Configuration

```php
// config/queue.php (Redis driver, relevant section)

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 180, // MUST be greater than longest job timeout (120s)
        'block_for' => null,
        'after_commit' => false,
    ],
],
```

```php
// config/horizon.php (if using Laravel Horizon for queue management)

'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'high'],
            'balance' => 'auto',
            'maxProcesses' => 10,
            'maxTime' => 3600,
            'maxJobs' => 500,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'supervisor-ai' => [
            // Dedicated supervisor for AI analysis jobs
            'connection' => 'redis',
            'queue' => ['ai-analysis'],
            'balance' => 'simple',
            'maxProcesses' => 5,      // Limit concurrent API calls
            'maxTime' => 3600,
            'maxJobs' => 100,
            'memory' => 512,          // AI jobs may need more memory
            'tries' => 3,
            'timeout' => 180,         // Allow extra time for AI
            'nice' => 0,
        ],
    ],
],
```

### Critical Configuration Rules

1. **`retry_after` must exceed `timeout`**: If `timeout` is 120s, set `retry_after` to at least 150-180s. Otherwise, the job gets retried while still running, causing duplicate API calls and wasted spend.

2. **Use dedicated queue for AI jobs**: Route AI analysis jobs to a separate queue (`ai-analysis`) with its own supervisor. This prevents AI jobs from blocking quick jobs like email sending.

3. **Idempotency is mandatory**: AI API calls cost money. The job must check if results already exist before making API calls. The `ShouldBeUnique` interface plus the idempotency check in `handle()` provide defense in depth.

### Dispatching with Idempotency

```php
// app/Actions/Assessment/CompleteAssessmentAction.php

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Jobs\AnalyzeAssessmentJob;
use Illuminate\Support\Str;

class CompleteAssessmentAction
{
    public function execute(Assessment $assessment): void
    {
        // Generate idempotency key based on assessment state
        $idempotencyKey = md5(
            $assessment->id .
            $assessment->answers()->count() .
            $assessment->answers()->latest()->value('updated_at')
        );

        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        AnalyzeAssessmentJob::dispatch($assessment, $idempotencyKey)
            ->onQueue('ai-analysis');
    }
}
```

---

## 3. Two-Phase AI Prompting: Structured Analysis then Narrative Synthesis

### Why Two Phases?

Single-prompt approaches fail for your use case because:
- A single prompt asking for both structured analysis AND narrative output produces mediocre results at both
- You lose the ability to validate intermediate data before generating the expensive narrative
- If the narrative fails, you lose the structured analysis too
- Two phases allow different models (fast/cheap for analysis, premium for narrative)

### Phase A: Structured Pattern Analysis

```php
// app/Services/ClaudeAnalysisService.php

namespace App\Services;

use App\Models\Assessment;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;

class ClaudeAnalysisService
{
    public function analyzePatterns(Assessment $assessment): array
    {
        $formattedResponses = $this->formatResponses($assessment);

        $response = Prism::structured()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
            ->withSchema($this->getAnalysisSchema())
            ->withSystemPrompt($this->getPatternAnalysisSystemPrompt())
            ->withPrompt($formattedResponses)
            ->withProviderOptions([
                'use_tool_calling' => true,
                'max_tokens' => 4096,
            ])
            ->asStructured();

        return $response->structured;
    }

    private function getPatternAnalysisSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a vocational discernment analyst with deep expertise in Reformed theology
of vocation, career counseling, and psychometric assessment interpretation.

You are analyzing 20 open-ended responses from a vocational discernment assessment
designed for ages 17-21. Your task is to identify patterns across 6 analytical
dimensions and map them to 17 vocational categories.

<analysis_framework>
The 6 dimensions you must analyze:
1. SERVICE ORIENTATION: How they naturally serve (direct care vs systemic vs creative)
2. PROBLEM-SOLVING DRAW: What disorder compels them (injustice, inefficiency, suffering, etc.)
3. ENERGY & ENGAGEMENT: Where their gifts lie (people/systems/ideas/things; creating/organizing/discovering/caring)
4. VALUES UNDER PRESSURE: How they weigh competing goods (duty vs calling vs risk vs security)
5. SUFFERING & LIMITATION: How they interpret obstacles (providence vs pure perseverance)
6. VISION & LEGACY: Scope of desired impact (local/global, immediate/generational)
</analysis_framework>

<vocational_categories>
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
</vocational_categories>

<critical_instructions>
- Base ALL analysis on evidence from the actual responses. Quote or reference specific answers.
- Identify the MULTI-DIMENSIONAL nature of their calling (primary domain + mode of work + secondary orientation).
- Do not reduce to a single category. Most people map to 2-4 categories in combination.
- Assign confidence scores honestly. Low confidence (< 0.5) is acceptable when responses are ambiguous.
- Look for patterns ACROSS responses, not just within individual answers.
- The category_rankings should have exactly 5 entries, ordered by fit score descending.
</critical_instructions>
PROMPT;
    }

    private function formatResponses(Assessment $assessment): string
    {
        $answers = $assessment->answers()
            ->with('question.category')
            ->orderBy('question_id')
            ->get();

        $formatted = "<assessment_responses>\n";

        foreach ($answers as $answer) {
            $formatted .= sprintf(
                "<response category=\"%s\" question_number=\"%d\">\n<question>%s</question>\n<answer>%s</answer>\n</response>\n\n",
                $answer->question->category->name,
                $answer->question->sort_order,
                $answer->question->question_text,
                $answer->response_text
            );
        }

        $formatted .= "</assessment_responses>";

        return $formatted;
    }
}
```

### Phase B: Narrative Synthesis

```php
// Continuation of ClaudeAnalysisService

public function synthesizeNarrative(Assessment $assessment, array $structuredAnalysis): array
{
    $formattedResponses = $this->formatResponses($assessment);

    $narrativeSchema = new ObjectSchema(
        name: 'vocational_narrative',
        description: 'The complete vocational profile narrative',
        properties: [
            new StringSchema('opening_synthesis', 'A 2-3 paragraph opening that synthesizes the overall picture. Written as a letter.'),
            new StringSchema('vocational_orientation', 'A narrative articulation of their vocational orientation. 3-5 paragraphs.'),
            new ArraySchema(
                name: 'primary_pathways',
                description: 'Specific vocational pathways with context',
                items: new ObjectSchema(
                    name: 'pathway',
                    description: 'A specific vocational pathway',
                    properties: [
                        new StringSchema('title', 'The pathway title'),
                        new StringSchema('description', 'Why this pathway fits, with specific evidence from responses'),
                        new StringSchema('trajectory', 'Suggested trajectory or progression'),
                    ],
                    requiredFields: ['title', 'description', 'trajectory']
                )
            ),
            new StringSchema('specific_considerations', 'Mode, trajectory, and secondary orientations explained. 2-4 paragraphs.'),
            new ArraySchema(
                name: 'next_steps',
                description: 'Ordered, formation-oriented next steps',
                items: new ObjectSchema(
                    name: 'step',
                    description: 'A next step',
                    properties: [
                        new StringSchema('action', 'The specific action'),
                        new StringSchema('reasoning', 'Why this step matters for their formation'),
                    ],
                    requiredFields: ['action', 'reasoning']
                )
            ),
            new StringSchema('ministry_integration', 'How their vocation IS ministry. 1-2 paragraphs.'),
        ],
        requiredFields: [
            'opening_synthesis', 'vocational_orientation', 'primary_pathways',
            'specific_considerations', 'next_steps', 'ministry_integration'
        ]
    );

    $response = Prism::structured()
        ->using(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->withSchema($narrativeSchema)
        ->withSystemPrompt($this->getNarrativeSynthesisSystemPrompt())
        ->withPrompt($this->buildNarrativePrompt($formattedResponses, $structuredAnalysis))
        ->withProviderOptions([
            'use_tool_calling' => true,
            'max_tokens' => 8192,
        ])
        ->asStructured();

    return $response->structured;
}

private function getNarrativeSynthesisSystemPrompt(): string
{
    return <<<'PROMPT'
You are a vocational discernment counselor writing a deeply personal vocational
profile. Your output should read like a letter from a wise mentor — not a test result.

<voice_guidelines>
- Write in second person ("You are drawn to..." not "The respondent is drawn to...")
- Be specific and personal, referencing themes from their actual responses
- Never use category labels as the primary framing (not "You are a CREATING & BUILDING type")
- Instead, describe the INTERSECTION of their domains, modes, and orientations
- Tone: warm, dignified, unhurried, theologically grounded but not preachy
- No exclamation marks. No hype. No flattery.
- Honor complexity. If there are tensions in their responses, name them gracefully.
</voice_guidelines>

<theological_grounding>
- All work is ministry when done faithfully
- Vocation is multi-dimensional (primary domain + mode of work + secondary orientation)
- Calling includes "station" (family, community responsibilities) per Luther
- Closed doors can be Providence, not just obstacles
- Cultural mandate = addressing disorder and cultivating creation
</theological_grounding>

<critical_rules>
- The opening_synthesis should feel like the beginning of a personal letter
- primary_pathways must be specific (not "something in healthcare" but "nursing with a trajectory toward nurse practitioner in underserved communities")
- next_steps must be sequenced and formation-oriented, not generic
- ministry_integration should connect their specific vocation to the theology of calling
- NEVER say "Great answer!" or provide validation feedback on individual responses
</critical_rules>
PROMPT;
}

private function buildNarrativePrompt(string $formattedResponses, array $structuredAnalysis): string
{
    $analysisJson = json_encode($structuredAnalysis, JSON_PRETTY_PRINT);

    return <<<PROMPT
Below are the original assessment responses and the structured pattern analysis.
Using both, generate a deeply personal vocational profile narrative.

<structured_analysis>
{$analysisJson}
</structured_analysis>

{$formattedResponses}

Generate the vocational profile narrative now. Remember: this should read like a
letter from a wise mentor, not a test result printout.
PROMPT;
}
```

### Chaining Best Practices

1. **Validate between phases**: After Phase A, validate the structured output before passing it to Phase B. If the analysis has low confidence scores across the board, consider requesting a re-analysis or flagging for human review.

2. **Use XML tags for context separation**: Claude responds well to `<analysis_framework>`, `<vocational_categories>`, `<response>`, and similar tags to separate different types of information.

3. **Phase A can use a faster model**: If cost is a concern, Phase A (structured pattern detection) can use Claude Haiku 4.5, while Phase B (narrative generation requiring nuance and empathy) should use Sonnet 4.5 or better.

4. **Cache the system prompt**: The system prompt for both phases is static and should be cached using prompt caching (see Section 9).

5. **Log intermediate outputs**: Always store the Phase A structured analysis in the `ai_analysis_raw` column. This enables debugging, evaluation, and future re-synthesis without re-running Phase A.

---

## 4. Handling AI Output Validation

### Defense in Depth Strategy

With Claude's structured outputs (constrained decoding), malformed JSON is essentially eliminated. But you still need validation for **semantic correctness** and **hallucinated content**.

#### Layer 1: Schema Enforcement (Structural Validation)

Claude's structured outputs guarantee that:
- Output is valid JSON
- All required fields are present
- Types match the schema
- No additional properties appear

This is handled automatically by the `output_config.format` parameter. No retry logic needed for structural issues.

#### Layer 2: Business Rule Validation (Semantic Validation)

```php
// app/Validators/VocationalAnalysisValidator.php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;

class VocationalAnalysisValidator
{
    private const VALID_CATEGORIES = [
        'Healing & Care', 'Teaching & Formation', 'Leadership & Management',
        'Law & Policy', 'Protecting & Defending', 'Creating & Building',
        'Maintaining & Repairing', 'Arts & Beauty', 'Discovering & Innovating',
        'Nourishing & Hospitality', 'Commerce & Enterprise', 'Finance & Economics',
        'Communication & Media', 'Advocating & Supporting', 'Knowledge & Information',
        'Administration & Systems', 'Pastoral & Missionary Work',
    ];

    public function validateStructuredAnalysis(array $analysis): array
    {
        $errors = [];

        // Validate category rankings reference real categories
        foreach ($analysis['category_rankings'] ?? [] as $i => $ranking) {
            if (! in_array($ranking['category'], self::VALID_CATEGORIES)) {
                $errors[] = "category_rankings[{$i}].category '{$ranking['category']}' is not a valid vocational category";
            }
            if ($ranking['score'] < 0 || $ranking['score'] > 100) {
                $errors[] = "category_rankings[{$i}].score must be between 0 and 100";
            }
        }

        // Validate exactly 5 category rankings
        if (count($analysis['category_rankings'] ?? []) !== 5) {
            $errors[] = 'category_rankings must contain exactly 5 entries';
        }

        // Validate confidence scores are between 0 and 1
        $dimensions = [
            'service_orientation', 'problem_solving', 'energy_sources',
            'values_decision_making', 'obstacle_response', 'vision_legacy',
        ];
        foreach ($dimensions as $dim) {
            $confidence = $analysis[$dim]['confidence'] ?? null;
            if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
                $errors[] = "{$dim}.confidence must be between 0 and 1";
            }
        }

        // Validate dimensional mapping is not empty
        foreach (['primary_domain', 'mode_of_work', 'secondary_orientation'] as $field) {
            if (empty($analysis['dimensional_mapping'][$field] ?? '')) {
                $errors[] = "dimensional_mapping.{$field} must not be empty";
            }
        }

        return $errors;
    }

    public function validateNarrativeProfile(array $narrative): array
    {
        $errors = [];

        // Minimum length checks (prevent degenerate outputs)
        if (strlen($narrative['opening_synthesis'] ?? '') < 200) {
            $errors[] = 'opening_synthesis is too short (minimum 200 characters)';
        }
        if (strlen($narrative['vocational_orientation'] ?? '') < 500) {
            $errors[] = 'vocational_orientation is too short (minimum 500 characters)';
        }

        // Ensure pathways are specific, not generic
        foreach ($narrative['primary_pathways'] ?? [] as $i => $pathway) {
            if (strlen($pathway['description'] ?? '') < 50) {
                $errors[] = "primary_pathways[{$i}].description is too short to be specific";
            }
        }

        // Minimum 3 pathways, maximum 6
        $pathwayCount = count($narrative['primary_pathways'] ?? []);
        if ($pathwayCount < 3 || $pathwayCount > 6) {
            $errors[] = "primary_pathways should have 3-6 entries, got {$pathwayCount}";
        }

        // Minimum 4 next steps
        if (count($narrative['next_steps'] ?? []) < 4) {
            $errors[] = 'next_steps should have at least 4 entries';
        }

        // Check for forbidden patterns (validation feedback)
        $forbiddenPatterns = [
            '/great answer/i',
            '/wonderful response/i',
            '/you scored/i',
            '/your personality type is/i',
            '/you are a .+ type/i',
        ];
        $fullText = implode(' ', array_filter([
            $narrative['opening_synthesis'] ?? '',
            $narrative['vocational_orientation'] ?? '',
            $narrative['specific_considerations'] ?? '',
            $narrative['ministry_integration'] ?? '',
        ]));
        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $fullText)) {
                $errors[] = "Narrative contains forbidden pattern: {$pattern}";
            }
        }

        return $errors;
    }
}
```

#### Layer 3: Retry with Feedback

```php
// In ClaudeAnalysisService, wrap with retry logic

public function analyzeWithRetry(Assessment $assessment, int $maxAttempts = 2): array
{
    $validator = new VocationalAnalysisValidator();

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $analysis = $this->analyzePatterns($assessment);
        $errors = $validator->validateStructuredAnalysis($analysis);

        if (empty($errors)) {
            return $analysis;
        }

        logger()->warning('AI analysis validation failed, retrying.', [
            'assessment_id' => $assessment->id,
            'attempt' => $attempt,
            'errors' => $errors,
        ]);

        // On retry, append validation feedback to help Claude self-correct
        if ($attempt < $maxAttempts) {
            // Could add error context to next prompt
            // But with structured outputs, this is rarely needed
        }
    }

    // If all attempts fail, return last result with a flag
    logger()->error('AI analysis validation failed after all attempts.', [
        'assessment_id' => $assessment->id,
        'errors' => $errors,
    ]);

    // Flag for human review rather than blocking the user
    $analysis['_validation_warnings'] = $errors;
    $analysis['_requires_review'] = true;

    return $analysis;
}
```

#### Layer 4: Hallucination Detection

For your specific use case (vocational analysis), hallucination manifests as:
- Claiming the user said something they did not say
- Referencing specific details not present in any response
- Inventing biographical details

```php
// Simple grounding check: verify that "evidence" fields reference actual response content
public function checkGrounding(array $analysis, Assessment $assessment): array
{
    $warnings = [];
    $responseTexts = $assessment->answers->pluck('response_text')->implode(' ');
    $responseLower = strtolower($responseTexts);

    foreach ($analysis['category_rankings'] as $ranking) {
        $evidence = strtolower($ranking['evidence'] ?? '');
        // Check if at least some keywords from the evidence appear in responses
        $evidenceWords = array_filter(explode(' ', $evidence), fn($w) => strlen($w) > 4);
        $matchCount = 0;
        foreach ($evidenceWords as $word) {
            if (str_contains($responseLower, $word)) {
                $matchCount++;
            }
        }
        $matchRatio = count($evidenceWords) > 0 ? $matchCount / count($evidenceWords) : 0;

        if ($matchRatio < 0.3) {
            $warnings[] = "category_rankings '{$ranking['category']}' evidence may not be grounded in actual responses (match ratio: {$matchRatio})";
        }
    }

    return $warnings;
}
```

---

## 5. Real-Time Audio Conversation Architecture

### Pipeline Architecture

The turn-based cascading architecture (STT -> LLM -> TTS) is the correct choice for your use case. Your conversation is not a rapid-fire chat; it is a thoughtful, paced conversation where 2-3 second response latency is acceptable and even desirable (it signals "thinking").

```
┌─────────────────────────────────────────────────────────────────────┐
│                    EXPO REACT NATIVE APP                            │
│                                                                     │
│  ┌─────────────┐    ┌──────────────┐    ┌────────────────────────┐ │
│  │ expo-audio   │    │ AudioOrb     │    │ Audio Playback         │ │
│  │ Recording    │───>│ Animation    │<───│ (TTS Response)         │ │
│  │              │    │ (Reanimated) │    │                        │ │
│  └──────┬───────┘    └──────────────┘    └────────────┬───────────┘ │
│         │                                             │             │
│         │ Upload audio chunk                          │ Stream TTS  │
│         ▼                                             │             │
└─────────┼─────────────────────────────────────────────┼─────────────┘
          │                                             │
          ▼                                             │
┌─────────────────────────────────────────────────────────────────────┐
│                      LARAVEL API                                    │
│                                                                     │
│  ┌──────────────────┐    ┌──────────────────┐    ┌───────────────┐ │
│  │ 1. WHISPER API    │    │ 2. CLAUDE API    │    │ 3. TTS API    │ │
│  │                   │    │                  │    │               │ │
│  │ Audio -> Text     │───>│ Evaluate depth   │───>│ Text -> Audio │ │
│  │                   │    │ Generate follow  │    │               │ │
│  │ ~1-2 seconds      │    │ up or advance    │    │ ~0.5-1 second │ │
│  │                   │    │ ~1-3 seconds     │    │               │ │
│  └──────────────────┘    └──────────────────┘    └───────────────┘ │
│                                                                     │
│  Total round-trip latency target: 3-5 seconds                       │
│  Acceptable for contemplative conversation pacing                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Server-Side Conversation Controller

```php
// app/Http/Controllers/Api/AudioConversationController.php

namespace App\Http\Controllers\Api;

use App\Models\Assessment;
use App\Models\ConversationSession;
use App\Services\WhisperTranscriptionService;
use App\Services\ClaudeAnalysisService;
use App\Services\TextToSpeechService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AudioConversationController extends Controller
{
    public function __construct(
        private WhisperTranscriptionService $whisper,
        private ClaudeAnalysisService $claude,
        private TextToSpeechService $tts,
    ) {}

    /**
     * Process a conversation turn:
     * 1. Transcribe user audio
     * 2. Evaluate response depth with Claude
     * 3. Generate follow-up or advance to next question
     * 4. Generate TTS audio for AI response
     */
    public function processTurn(Request $request, ConversationSession $session)
    {
        $request->validate([
            'audio' => 'required|file|mimes:wav,m4a,mp3,webm|max:25600',
        ]);

        // Step 1: Store audio and transcribe
        $audioPath = $request->file('audio')->store(
            "conversations/{$session->id}",
            's3'
        );

        $transcript = $this->whisper->transcribe(
            Storage::disk('s3')->path($audioPath)
        );

        // Step 2: Save the turn
        $currentQuestion = $session->getCurrentQuestion();
        $session->addTurn('user', $transcript, $audioPath);

        // Step 3: Evaluate depth and determine next action
        $evaluation = $this->claude->evaluateConversationTurn(
            session: $session,
            currentQuestion: $currentQuestion,
            userResponse: $transcript,
        );

        // Step 4: Generate AI response
        if ($evaluation['advance_to_next']) {
            // Save answer
            $session->assessment->answers()->updateOrCreate(
                ['question_id' => $currentQuestion->id],
                [
                    'response_text' => $this->compileResponseText($session, $currentQuestion),
                    'audio_transcript' => $transcript,
                    'audio_storage_path' => $audioPath,
                ]
            );

            $session->advanceQuestion();

            if ($session->isComplete()) {
                $aiResponse = "Thank you for sharing so openly. I've heard everything you've said, and I can see real patterns emerging across your responses. Let me take some time to reflect on what you've shared and put together something meaningful for you.";
                $session->update(['status' => 'completed']);
            } else {
                $nextQuestion = $session->getCurrentQuestion();
                $aiResponse = $nextQuestion->conversation_prompt;
            }
        } else {
            // Ask follow-up
            $aiResponse = $evaluation['follow_up_question'];
        }

        $session->addTurn('assistant', $aiResponse);

        // Step 5: Generate TTS
        $ttsAudioPath = $this->tts->synthesize($aiResponse, $session->id);

        return response()->json([
            'transcript' => $transcript,
            'ai_response_text' => $aiResponse,
            'ai_response_audio_url' => Storage::disk('s3')->temporaryUrl($ttsAudioPath, now()->addMinutes(30)),
            'current_question_index' => $session->current_question_index,
            'is_complete' => $session->isComplete(),
        ]);
    }
}
```

### Whisper Transcription Service

```php
// app/Services/WhisperTranscriptionService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhisperTranscriptionService
{
    public function transcribe(string $audioFilePath): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->attach('file', file_get_contents($audioFilePath), 'audio.wav')
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'text',
                'language' => 'en',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Whisper transcription failed: {$response->body()}"
            );
        }

        return trim($response->body());
    }
}
```

### Latency Budget

| Component | Target Latency | Notes |
|-----------|---------------|-------|
| Audio upload | 500ms-1s | Depends on file size and network |
| Whisper STT | 1-2s | Server-side API call |
| Claude evaluation | 1-3s | Use Haiku for speed |
| TTS synthesis | 0.5-1s | ElevenLabs or OpenAI TTS |
| Audio download | 500ms-1s | Pre-signed S3 URL |
| **Total** | **3-6s** | **Acceptable for contemplative pacing** |

### Optimization: Use Claude Haiku for Conversation Turns

For the real-time conversation evaluation (should I ask a follow-up or advance?), use Claude Haiku 4.5 (~100ms time-to-first-token). Reserve Sonnet 4.5 for the final assessment analysis. This dramatically reduces per-turn latency.

### On-Device Alternative: whisper.rn

For lower latency, consider on-device transcription using `whisper.rn` (React Native binding for whisper.cpp). This eliminates the network round-trip for STT:

```typescript
// mobile/hooks/useAudioConversation.ts

import { initWhisper, TranscribeResult } from 'whisper.rn';

const useOnDeviceTranscription = () => {
  const whisperContext = useRef<WhisperContext | null>(null);

  useEffect(() => {
    // Load Whisper model on mount (small model ~40MB)
    initWhisper({
      filePath: 'path/to/ggml-small.bin',
    }).then(ctx => {
      whisperContext.current = ctx;
    });
  }, []);

  const transcribe = async (audioUri: string): Promise<string> => {
    if (!whisperContext.current) throw new Error('Whisper not loaded');

    const result: TranscribeResult = await whisperContext.current.transcribe(audioUri, {
      language: 'en',
      maxLen: 0,
      translate: false,
    });

    return result.result;
  };

  return { transcribe };
};
```

---

## 6. Laravel Multi-Tenant SaaS Patterns

### Recommendation: Shared Database with Tenant Scopes

For Vocation-Finder, the shared database approach is the right choice. Your tenants (churches, universities, nonprofits) are not enterprise customers with strict data isolation requirements. They share the same schema, same assessment questions, and same AI analysis pipeline.

### Implementation with Global Scopes

```php
// app/Traits/BelongsToOrganization.php

namespace App\Traits;

use App\Models\Organization;
use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->organization_id) {
                $model->organization_id = $model->organization_id
                    ?? auth()->user()->organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
```

```php
// app/Scopes/OrganizationScope.php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply scope if user is authenticated and belongs to an org
        if (auth()->check() && auth()->user()->organization_id) {
            $builder->where(
                $model->getTable() . '.organization_id',
                auth()->user()->organization_id
            );
        }
    }
}
```

```php
// app/Models/User.php (relevant parts)

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    // Note: Users do NOT use the BelongsToOrganization trait.
    // Users are the entry point for determining tenant context.

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'super_admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
```

```php
// app/Models/Assessment.php (uses the trait)

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Assessment extends Model
{
    use HasUuids, BelongsToOrganization;

    // All queries automatically scoped to current user's organization
}
```

### Middleware for Tenant Context

```php
// app/Http/Middleware/SetTenantContext.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->organization_id) {
            // Make organization available globally
            app()->singleton('current_organization', function () use ($request) {
                return $request->user()->organization;
            });
        }

        return $next($request);
    }
}
```

### Migration Pattern

```php
// Add organization_id to assessments table

Schema::table('assessments', function (Blueprint $table) {
    $table->foreignUuid('organization_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();
    $table->index('organization_id');
});
```

### Individual Users vs Organization Members

Your system supports both individual users (no organization) and organization members. The scope only applies when the user has an `organization_id`. Individual users see only their own data (handled by standard `user_id` scoping on assessments).

### When to Consider Separate Databases

Move to separate databases ONLY if:
- An enterprise customer contractually requires data isolation
- You need to store data in a specific geographic region per tenant
- Query performance degrades beyond what indexing can fix (unlikely at your scale)

For the first 2-3 years and up to thousands of organizations, shared database with global scopes is the right approach.

---

## 7. Assessment Platform Architecture: Autosave, Progress, Resume

### Autosave Strategy

Your design spec says "autosave every keystroke." In practice, this means **debounced autosave** to avoid overwhelming the API.

#### Client-Side (Expo/React Native)

```typescript
// mobile/hooks/useAssessment.ts

import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { debounce } from 'lodash';

interface Answer {
  questionId: string;
  text: string;
  savedToServer: boolean;
  lastModified: number;
}

interface AssessmentState {
  assessmentId: string | null;
  currentQuestionIndex: number;
  answers: Record<string, Answer>;
  setAnswer: (questionId: string, text: string) => void;
  syncToServer: () => Promise<void>;
  resumeAssessment: () => void;
}

export const useAssessmentStore = create<AssessmentState>()(
  persist(
    (set, get) => ({
      assessmentId: null,
      currentQuestionIndex: 0,
      answers: {},

      setAnswer: (questionId: string, text: string) => {
        set((state) => ({
          answers: {
            ...state.answers,
            [questionId]: {
              questionId,
              text,
              savedToServer: false,
              lastModified: Date.now(),
            },
          },
        }));

        // Debounced sync to server
        debouncedSync();
      },

      syncToServer: async () => {
        const state = get();
        if (!state.assessmentId) return;

        const unsaved = Object.values(state.answers).filter(a => !a.savedToServer);
        if (unsaved.length === 0) return;

        try {
          await api.post(`/assessments/${state.assessmentId}/answers/batch`, {
            answers: unsaved.map(a => ({
              question_id: a.questionId,
              response_text: a.text,
            })),
          });

          // Mark as saved
          set((state) => {
            const updated = { ...state.answers };
            unsaved.forEach(a => {
              if (updated[a.questionId]) {
                updated[a.questionId].savedToServer = true;
              }
            });
            return { answers: updated };
          });
        } catch (error) {
          // Will retry on next debounce trigger
          console.warn('Autosave failed, will retry:', error);
        }
      },

      resumeAssessment: () => {
        // On app launch, check for in-progress assessment
        const state = get();
        if (state.assessmentId && Object.keys(state.answers).length > 0) {
          // Find the last answered question and resume from there
          const answeredIndices = Object.values(state.answers)
            .filter(a => a.text.trim().length > 0)
            .length;
          set({ currentQuestionIndex: Math.max(0, answeredIndices - 1) });
        }
      },
    }),
    {
      name: 'vocation-assessment',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        assessmentId: state.assessmentId,
        currentQuestionIndex: state.currentQuestionIndex,
        answers: state.answers,
      }),
    }
  )
);

// Debounce: save at most every 2 seconds
const debouncedSync = debounce(() => {
  useAssessmentStore.getState().syncToServer();
}, 2000);
```

#### Server-Side Batch Save Endpoint

```php
// app/Http/Controllers/Api/AssessmentController.php

public function batchSaveAnswers(Request $request, Assessment $assessment)
{
    $request->validate([
        'answers' => 'required|array',
        'answers.*.question_id' => 'required|uuid|exists:questions,id',
        'answers.*.response_text' => 'required|string',
    ]);

    $saved = [];

    foreach ($request->answers as $answerData) {
        $answer = $assessment->answers()->updateOrCreate(
            ['question_id' => $answerData['question_id']],
            [
                'response_text' => $answerData['response_text'],
                'updated_at' => now(),
            ]
        );
        $saved[] = $answer->id;
    }

    return response()->json([
        'saved_count' => count($saved),
        'saved_ids' => $saved,
    ]);
}
```

### Progress Tracking

```php
// app/Http/Resources/AssessmentResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray($request): array
    {
        $totalQuestions = 20;
        $answeredCount = $this->answers()->whereNotNull('response_text')
            ->where('response_text', '!=', '')
            ->count();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'mode' => $this->mode,
            'progress' => [
                'answered' => $answeredCount,
                'total' => $totalQuestions,
                // No percentage shown in UI per design spec, but API can provide it
                'percentage' => round(($answeredCount / $totalQuestions) * 100),
                'current_question_index' => $this->getCurrentQuestionIndex(),
            ],
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'can_resume' => $this->status === 'in_progress',
        ];
    }
}
```

### Resume Flow

```
User opens app
    -> Check Zustand persisted state
        -> If assessmentId exists and status is "in_progress":
            -> Fetch /api/assessments/{id} to verify server state
            -> If server says "in_progress": resume at last question
            -> If server says "completed": show results
            -> If server says "abandoned" or 404: start fresh
        -> If no assessmentId: show landing page
```

### Abandonment Detection

```php
// app/Console/Commands/DetectAbandonedAssessments.php
// Run via scheduler every hour

namespace App\Console\Commands;

use App\Models\Assessment;
use Illuminate\Console\Command;

class DetectAbandonedAssessments extends Command
{
    protected $signature = 'assessments:detect-abandoned';

    public function handle(): void
    {
        // Mark assessments as abandoned if no activity for 7 days
        Assessment::where('status', 'in_progress')
            ->where('updated_at', '<', now()->subDays(7))
            ->update(['status' => 'abandoned']);

        // Send reminder for assessments idle 24-48 hours
        Assessment::where('status', 'in_progress')
            ->whereBetween('updated_at', [now()->subHours(48), now()->subHours(24)])
            ->each(function (Assessment $assessment) {
                // Send push notification or email reminder
            });
    }
}
```

---

## 8. Offline-First Patterns for Assessment Tools

### Architecture: Zustand + AsyncStorage + Sync Queue

The written assessment mode should work fully offline. The audio conversation mode requires connectivity (STT + LLM + TTS all need network).

#### Offline State Machine

```
ONLINE:
  User types -> Zustand updates -> Debounced API sync -> AsyncStorage backup

OFFLINE:
  User types -> Zustand updates -> AsyncStorage save -> Queue sync operation

RECONNECT:
  Detect connectivity -> Drain sync queue -> Reconcile with server
```

#### Sync Queue Implementation

```typescript
// mobile/services/syncQueue.ts

import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { api } from './api';

interface SyncOperation {
  id: string;
  type: 'save_answer' | 'complete_assessment' | 'start_assessment';
  payload: Record<string, any>;
  createdAt: number;
  retryCount: number;
}

class SyncQueue {
  private queue: SyncOperation[] = [];
  private isProcessing = false;
  private storageKey = 'vocation-sync-queue';

  constructor() {
    this.loadQueue();
    this.setupNetworkListener();
  }

  private async loadQueue(): Promise<void> {
    const stored = await AsyncStorage.getItem(this.storageKey);
    if (stored) {
      this.queue = JSON.parse(stored);
    }
  }

  private async saveQueue(): Promise<void> {
    await AsyncStorage.setItem(this.storageKey, JSON.stringify(this.queue));
  }

  private setupNetworkListener(): void {
    NetInfo.addEventListener((state) => {
      if (state.isConnected && this.queue.length > 0) {
        this.processQueue();
      }
    });
  }

  async enqueue(operation: Omit<SyncOperation, 'id' | 'createdAt' | 'retryCount'>): Promise<void> {
    this.queue.push({
      ...operation,
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      createdAt: Date.now(),
      retryCount: 0,
    });
    await this.saveQueue();

    // Try to process immediately if online
    const netState = await NetInfo.fetch();
    if (netState.isConnected) {
      this.processQueue();
    }
  }

  private async processQueue(): Promise<void> {
    if (this.isProcessing || this.queue.length === 0) return;
    this.isProcessing = true;

    const processed: string[] = [];

    for (const op of this.queue) {
      try {
        await this.executeOperation(op);
        processed.push(op.id);
      } catch (error) {
        op.retryCount++;
        if (op.retryCount >= 5) {
          processed.push(op.id); // Remove after max retries
          console.error('Sync operation permanently failed:', op);
        }
        break; // Stop processing on failure to maintain order
      }
    }

    this.queue = this.queue.filter(op => !processed.includes(op.id));
    await this.saveQueue();
    this.isProcessing = false;
  }

  private async executeOperation(op: SyncOperation): Promise<void> {
    switch (op.type) {
      case 'save_answer':
        await api.post(
          `/assessments/${op.payload.assessmentId}/answers/batch`,
          { answers: op.payload.answers }
        );
        break;
      case 'complete_assessment':
        await api.post(`/assessments/${op.payload.assessmentId}/complete`);
        break;
      case 'start_assessment':
        await api.post('/assessments', op.payload);
        break;
    }
  }
}

export const syncQueue = new SyncQueue();
```

#### Offline UI Indicator

Per the design spec, the UI should be minimal. An offline indicator should be subtle:

```typescript
// mobile/components/ui/OfflineIndicator.tsx

import React from 'react';
import { Text, View } from 'react-native';
import { useNetInfo } from '@react-native-community/netinfo';

export function OfflineIndicator() {
  const netInfo = useNetInfo();

  if (netInfo.isConnected !== false) return null;

  return (
    <View style={{ paddingVertical: 4, alignItems: 'center' }}>
      <Text style={{
        fontFamily: 'Inter',
        fontSize: 12,
        color: '#9B9B9B',
        letterSpacing: 0.5,
      }}>
        Your answers are saved locally and will sync when you reconnect.
      </Text>
    </View>
  );
}
```

### Conflict Resolution

Since only one user edits their own assessment, true merge conflicts are unlikely. The resolution strategy is **last-write-wins** with timestamp comparison:

```php
// Server-side: accept update only if client timestamp is newer
public function batchSaveAnswers(Request $request, Assessment $assessment)
{
    foreach ($request->answers as $answerData) {
        $existing = $assessment->answers()
            ->where('question_id', $answerData['question_id'])
            ->first();

        // Only update if client version is newer
        if (! $existing || $existing->updated_at->timestamp < ($answerData['last_modified'] / 1000)) {
            $assessment->answers()->updateOrCreate(
                ['question_id' => $answerData['question_id']],
                ['response_text' => $answerData['response_text']],
            );
        }
    }
}
```

---

## 9. AI Cost Optimization

### Current Pricing Context (2025-2026)

| Model | Input (per 1M tokens) | Output (per 1M tokens) | Use Case |
|-------|----------------------|------------------------|----------|
| Claude Haiku 4.5 | $0.80 | $4.00 | Conversation turn evaluation |
| Claude Sonnet 4.5 | $3.00 | $15.00 | Assessment analysis + narrative |
| Claude Opus 4.6 | $5.00 | $25.00 | Reserve for edge cases |

### Cost Per Assessment Estimate

For a single assessment (20 responses analyzed):

| Component | Input Tokens | Output Tokens | Cost (Sonnet 4.5) |
|-----------|-------------|---------------|-------------------|
| Phase A system prompt | ~2,000 | - | ~$0.006 |
| Phase A user content (20 responses) | ~4,000-8,000 | - | ~$0.024 |
| Phase A output (structured JSON) | - | ~2,000 | ~$0.030 |
| Phase B system prompt | ~1,500 | - | ~$0.005 |
| Phase B user content (responses + analysis) | ~8,000-12,000 | - | ~$0.036 |
| Phase B output (narrative) | - | ~4,000 | ~$0.060 |
| **Total per assessment** | **~16,000-24,000** | **~6,000** | **~$0.16** |

This is remarkably affordable. Even at $0.20 per assessment, 10,000 assessments cost only $2,000.

### Optimization Strategy 1: Prompt Caching

The system prompts for Phase A and Phase B are identical across all assessments. Cache them.

```php
// Using direct HTTP client for prompt caching support

$response = Http::withHeaders([
    'x-api-key' => config('services.anthropic.key'),
    'anthropic-version' => '2023-06-01',
])->post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 4096,
    'system' => [
        [
            'type' => 'text',
            'text' => $patternAnalysisSystemPrompt, // ~2000 tokens, cached
            'cache_control' => ['type' => 'ephemeral'], // 5-minute TTL
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => $formattedResponses],
    ],
    'output_config' => [
        'format' => [
            'type' => 'json_schema',
            'schema' => $analysisSchema,
        ],
    ],
]);
```

**Savings with caching:** System prompt reads at 0.1x cost. If processing multiple assessments within 5 minutes (burst scenarios during organization group sessions), the ~2,000 token system prompt costs $0.0006 instead of $0.006 per call. The 1-hour TTL option (at 2x base input) may be better for sustained processing.

### Optimization Strategy 2: Batch API for Organization Reports

When an organization with 50+ members completes assessments, use the Batch API for aggregate analysis:

```php
// app/Services/BatchAnalysisService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BatchAnalysisService
{
    public function submitBatch(array $assessments): string
    {
        $requests = [];

        foreach ($assessments as $i => $assessment) {
            $requests[] = [
                'custom_id' => "assessment-{$assessment->id}",
                'params' => [
                    'model' => 'claude-sonnet-4-5-20250929',
                    'max_tokens' => 4096,
                    'system' => $this->getSystemPrompt(),
                    'messages' => [
                        ['role' => 'user', 'content' => $this->formatResponses($assessment)],
                    ],
                    'output_config' => [
                        'format' => [
                            'type' => 'json_schema',
                            'schema' => $this->getAnalysisSchema(),
                        ],
                    ],
                ],
            ];
        }

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages/batches', [
            'requests' => $requests,
        ]);

        return $response->json('id'); // Batch ID for polling
    }

    public function checkBatchStatus(string $batchId): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->get("https://api.anthropic.com/v1/messages/batches/{$batchId}");

        return $response->json();
    }
}
```

**Savings:** 50% off both input and output tokens. A batch of 50 assessments at $0.16 each = $8.00 standard, $4.00 with Batch API.

### Optimization Strategy 3: Model Routing

Use the cheapest model that provides sufficient quality for each task:

| Task | Model | Rationale |
|------|-------|-----------|
| Conversation follow-up evaluation | Haiku 4.5 | Simple decision: advance or follow up |
| Phase A: Pattern analysis | Sonnet 4.5 | Needs analytical depth |
| Phase B: Narrative synthesis | Sonnet 4.5 | Needs empathy and writing quality |
| Organization aggregate reports | Sonnet 4.5 via Batch API | Not time-sensitive |

### Optimization Strategy 4: Response Caching

If a user retakes the assessment with identical answers (unlikely but possible), cache the analysis:

```php
public function analyzePatterns(Assessment $assessment): array
{
    $cacheKey = 'vocational-analysis:' . md5(
        $assessment->answers()
            ->orderBy('question_id')
            ->pluck('response_text')
            ->implode('|')
    );

    return Cache::remember($cacheKey, now()->addDays(30), function () use ($assessment) {
        return $this->callClaudeForAnalysis($assessment);
    });
}
```

### Optimization Strategy 5: Output Token Management

Output tokens cost 5x more than input tokens. Control output length:
- Phase A structured output: schema constraints naturally limit length
- Phase B narrative: set `max_tokens` to 8192 (sufficient for a thorough profile, prevents runaway generation)
- Use system prompt instructions: "Be thorough but concise. Opening synthesis: 2-3 paragraphs. Vocational orientation: 3-5 paragraphs."

---

## 10. Psychometric Assessment Data Modeling

### Key Principles for Your Assessment

Your assessment is unique because it uses open-ended narrative responses analyzed by AI, not traditional Likert-scale or forced-choice items. This affects data modeling significantly.

### Schema Design for Psychometric Rigor

```php
// database/migrations/create_assessment_tables.php

// Core response storage with quality metrics
Schema::create('answers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('question_id')->constrained();
    $table->text('response_text');
    $table->text('audio_transcript')->nullable();
    $table->string('audio_storage_path')->nullable();

    // Psychometric metadata
    $table->integer('response_time_seconds')->nullable();  // Time spent on this question
    $table->integer('word_count')->nullable();              // Response depth indicator
    $table->integer('revision_count')->default(0);          // How many times edited
    $table->timestamp('first_keystroke_at')->nullable();     // Latency to start
    $table->timestamp('last_keystroke_at')->nullable();      // When they finished

    // AI per-answer analysis (populated during Phase A)
    $table->json('ai_preliminary_analysis')->nullable();

    $table->timestamps();

    $table->unique(['assessment_id', 'question_id']);
});

// Assessment metadata for reliability analysis
Schema::create('assessment_metadata', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();

    // Session behavior data
    $table->integer('total_duration_seconds')->nullable();
    $table->integer('pause_count')->default(0);            // Times they left and came back
    $table->integer('longest_pause_seconds')->nullable();
    $table->string('device_type')->nullable();              // mobile|tablet|desktop
    $table->string('assessment_mode');                       // conversation|written
    $table->json('question_order')->nullable();             // If randomized in future

    // Response pattern indicators
    $table->float('average_response_time')->nullable();
    $table->float('average_word_count')->nullable();
    $table->float('response_time_variance')->nullable();    // High variance may indicate disengagement
    $table->integer('skipped_then_returned_count')->default(0);

    $table->timestamps();
});

// For test-retest reliability tracking
Schema::create('assessment_comparisons', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained();
    $table->foreignUuid('assessment_a_id')->constrained('assessments');
    $table->foreignUuid('assessment_b_id')->constrained('assessments');

    // Stability metrics
    $table->float('category_ranking_correlation')->nullable(); // Spearman's rho
    $table->float('dimensional_mapping_similarity')->nullable();
    $table->json('category_changes')->nullable();              // Which categories shifted
    $table->integer('days_between')->nullable();

    $table->timestamps();
});

// Aggregate norms for population comparison
Schema::create('population_norms', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('demographic_group');                    // age_17_18, age_19_21, etc.
    $table->string('vocational_category');
    $table->float('mean_score');
    $table->float('standard_deviation');
    $table->integer('sample_size');
    $table->date('calculated_at');

    $table->timestamps();
});
```

### Response Quality Scoring

```php
// app/Services/ResponseQualityService.php

namespace App\Services;

use App\Models\Answer;

class ResponseQualityService
{
    /**
     * Score response quality to flag potentially unreliable assessments.
     * This does NOT affect the user experience (no validation feedback per design spec).
     * It is used internally to weight confidence in analysis.
     */
    public function scoreResponseQuality(Answer $answer): array
    {
        $text = $answer->response_text;
        $wordCount = str_word_count($text);
        $responseTime = $answer->response_time_seconds;

        return [
            // Depth: longer responses generally indicate more engagement
            'depth_score' => $this->depthScore($wordCount),

            // Specificity: presence of specific details vs generic statements
            'specificity_score' => $this->specificityScore($text),

            // Engagement: appropriate time spent (not too fast, not too slow)
            'engagement_score' => $this->engagementScore($responseTime, $wordCount),

            // Coherence: basic text quality indicators
            'coherence_score' => $this->coherenceScore($text),

            // Overall quality flag
            'quality_flag' => $this->qualityFlag($wordCount, $responseTime),
        ];
    }

    private function depthScore(int $wordCount): float
    {
        // 50-300 words is ideal for most questions
        if ($wordCount < 15) return 0.2;
        if ($wordCount < 30) return 0.4;
        if ($wordCount < 50) return 0.6;
        if ($wordCount <= 300) return 1.0;
        return 0.8; // Very long may indicate rambling
    }

    private function specificityScore(string $text): float
    {
        // Check for specific indicators: numbers, proper nouns, concrete details
        $specificIndicators = 0;

        // Contains specific examples (signaled by temporal/descriptive phrases)
        $specificPhrases = [
            '/when I was/', '/last year/', '/in my/', '/for example/',
            '/specifically/', '/I remember/', '/one time/', '/there was a/',
        ];

        foreach ($specificPhrases as $pattern) {
            if (preg_match($pattern, strtolower($text))) {
                $specificIndicators++;
            }
        }

        return min(1.0, $specificIndicators / 3);
    }

    private function engagementScore(?int $seconds, int $wordCount): float
    {
        if (! $seconds) return 0.5; // Unknown

        // Average typing speed: ~40 WPM. But thinking time should add 2-3x.
        $expectedMinSeconds = ($wordCount / 40) * 60;
        $expectedMaxSeconds = $expectedMinSeconds * 5;

        if ($seconds < $expectedMinSeconds * 0.3) return 0.3; // Suspiciously fast
        if ($seconds > $expectedMaxSeconds) return 0.6;        // Very slow (might indicate distraction)
        return 1.0;
    }

    private function coherenceScore(string $text): float
    {
        // Basic checks: sentence count, average sentence length
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);

        if ($sentenceCount < 2) return 0.5;
        if ($sentenceCount > 20) return 0.7;
        return 1.0;
    }

    private function qualityFlag(int $wordCount, ?int $responseTime): string
    {
        if ($wordCount < 10) return 'insufficient';
        if ($wordCount < 25) return 'minimal';
        if ($responseTime && $responseTime < 10) return 'rushed';
        return 'adequate';
    }
}
```

### Dimensional Scoring Model

Your 17 vocational categories are scored by AI rather than by traditional item-response mapping. To maintain psychometric validity:

```php
// app/Models/VocationalProfile.php (relevant methods)

class VocationalProfile extends Model
{
    protected $casts = [
        'primary_pathways' => 'array',
        'next_steps' => 'array',
        'ai_analysis_raw' => 'array',
        'dimensional_mapping' => 'array',
        'category_scores' => 'array',
    ];

    /**
     * Get the confidence-weighted category scores.
     * Lower confidence dimensions contribute less to final rankings.
     */
    public function getWeightedCategoryScores(): array
    {
        $analysis = $this->ai_analysis_raw;
        $categories = $this->category_scores;

        // Calculate average confidence across dimensions
        $dimensions = [
            'service_orientation', 'problem_solving', 'energy_sources',
            'values_decision_making', 'obstacle_response', 'vision_legacy',
        ];

        $avgConfidence = collect($dimensions)
            ->map(fn($dim) => $analysis[$dim]['confidence'] ?? 0.5)
            ->average();

        // Weight category scores by overall confidence
        return collect($categories)->map(function ($category) use ($avgConfidence) {
            return [
                ...$category,
                'weighted_score' => $category['score'] * $avgConfidence,
                'confidence_factor' => $avgConfidence,
            ];
        })->sortByDesc('weighted_score')->values()->toArray();
    }

    /**
     * Compare with population norms for percentile ranking.
     */
    public function getPercentileRankings(string $demographicGroup): array
    {
        $norms = PopulationNorm::where('demographic_group', $demographicGroup)->get();

        return collect($this->category_scores)->map(function ($category) use ($norms) {
            $norm = $norms->firstWhere('vocational_category', $category['category']);
            if (! $norm) return $category;

            // Calculate z-score and percentile
            $zScore = ($category['score'] - $norm->mean_score) / max($norm->standard_deviation, 0.01);
            $percentile = $this->zScoreToPercentile($zScore);

            return [
                ...$category,
                'percentile' => $percentile,
                'z_score' => round($zScore, 2),
            ];
        })->toArray();
    }

    private function zScoreToPercentile(float $z): int
    {
        // Approximation of the CDF for standard normal distribution
        return (int) round(100 * (0.5 * (1 + erf($z / sqrt(2)))));
    }
}
```

### Data Retention and Privacy

Assessment responses are deeply personal. Model your data retention accordingly:

```php
// config/vocation.php

return [
    'data_retention' => [
        // How long to keep raw audio recordings
        'audio_recordings_days' => 90,

        // How long to keep individual response texts after analysis
        // (vocational profile is kept, raw responses are purged)
        'raw_responses_days' => 365,

        // How long to keep the AI analysis raw output
        'ai_analysis_raw_days' => 365,

        // Vocational profiles are kept indefinitely (the product value)
    ],

    'gdpr' => [
        // Users can request deletion at any time
        'deletion_grace_period_days' => 30,

        // Export format for GDPR data portability
        'export_format' => 'json',
    ],
];
```

---

## Sources

### Laravel + Claude Integration
- [Prism PHP - Introduction](https://prismphp.com/getting-started/introduction.html) -- Laravel-native AI abstraction layer
- [Prism PHP - Structured Output](https://prismphp.com/core-concepts/structured-output.html) -- Schema definitions and structured responses
- [Prism PHP - Anthropic Provider](https://prismphp.com/providers/anthropic.html) -- Claude-specific configuration
- [Prism PHP - Tools & Function Calling](https://prismphp.com/core-concepts/tools-function-calling.html) -- Tool use patterns
- [goldenpathdigital/laravel-claude on Packagist](https://packagist.org/packages/goldenpathdigital/laravel-claude) -- Official Anthropic PHP SDK Laravel wrapper
- [Claude PHP SDK on GitHub](https://github.com/claude-php/Claude-PHP-SDK) -- Community PHP SDK
- [Prism Makes AI Feel Laravel Native - Laravel Blog](https://laravel.com/blog/prism-makes-ai-feel-laravel-native-the-artisan-of-the-day-is-tj-miller) -- Official Laravel endorsement

### Claude API Structured Outputs
- [Structured Outputs - Claude API Docs](https://platform.claude.com/docs/en/build-with-claude/structured-outputs) -- GA feature, constrained decoding, `output_config.format`
- [Anthropic Launches Structured Outputs - Tech Bytes](https://techbytes.app/posts/claude-structured-outputs-json-schema-api/) -- Feature announcement and capabilities
- [Hands-On with Structured Outputs - Towards Data Science](https://towardsdatascience.com/hands-on-with-anthropics-new-structured-output-capabilities/) -- Practical implementation guide

### Queue Architecture
- [Laravel Queues Documentation](https://laravel.com/docs/12.x/queues) -- Official docs for retry, timeout, unique jobs
- [Deep Dive into Laravel Queue Settings](https://www.vincentschmalbach.com/deep-dive-into-laravel-queue-settings-retry_after-timeout-and-backoff/) -- `retry_after` vs `timeout` relationship
- [How Laravel Fails and Retries Queued Jobs](https://sjorso.com/how-laravel-fails-and-retries-queued-jobs) -- Job failure lifecycle
- [How to Handle Long-Running Jobs in Laravel](https://cosme.dev/post/how-to-handle-longrunning-jobs-in-laravel) -- Strategies for AI-scale jobs

### Prompt Engineering
- [Prompting Best Practices - Claude API Docs](https://platform.claude.com/docs/en/build-with-claude/prompt-engineering/claude-prompting-best-practices) -- XML tags, examples, chaining
- [Extended Thinking Tips](https://platform.claude.com/docs/en/build-with-claude/prompt-engineering/extended-thinking-tips) -- When to use extended thinking
- [Prompt Caching - Claude API Docs](https://platform.claude.com/docs/en/build-with-claude/prompt-caching) -- `cache_control` parameter, pricing, TTL

### AI Cost Optimization
- [Claude API Pricing](https://platform.claude.com/docs/en/about-claude/pricing) -- Current model pricing
- [Message Batches API - Claude Blog](https://claude.com/blog/message-batches-api) -- 50% discount batch processing
- [Save 90% on Claude API Costs - DEV Community](https://dev.to/stklen/how-to-save-90-on-claude-api-costs-3-official-techniques-3d4n) -- Combined optimization strategies
- [Reduce LLM Costs: Token Optimization](https://www.glukhov.org/post/2025/11/cost-effective-llm-applications/) -- Prompt compression and routing

### Audio Pipeline
- [Real-Time vs Turn-Based Voice Agent Architecture - Softcery](https://softcery.com/lab/ai-voice-agents-real-time-vs-turn-based-tts-stt-architecture) -- Architecture comparison
- [Voice AI Infrastructure - Introl](https://introl.com/blog/voice-ai-infrastructure-real-time-speech-agents-asr-tts-guide-2025) -- Latency budgets and optimization
- [Designing Voice AI Workflows - Deepgram](https://deepgram.com/learn/designing-voice-ai-workflows-using-stt-nlp-tts) -- STT + NLP + TTS pipeline design
- [expo-speech-recognition on GitHub](https://github.com/jamsch/expo-speech-recognition) -- Expo speech recognition module
- [whisper.rn on GitHub](https://github.com/mybigday/whisper.rn) -- On-device Whisper for React Native
- [Expo Audio Documentation](https://docs.expo.dev/versions/latest/sdk/audio/) -- Audio recording and playback

### Multi-Tenant SaaS
- [Tenancy for Laravel - Official Docs](https://tenancyforlaravel.com/) -- stancl/tenancy package
- [Single-Database Tenancy](https://tenancyforlaravel.com/docs/v3/single-database-tenancy/) -- BelongsToTenant trait approach
- [Multi-Tenant SaaS in Laravel 2026 Edition - Codeboxr](https://codeboxr.com/multi-tenant-saas-in-laravel-a-practical-step-by-step-implementation-guide-2026-edition/) -- Comprehensive implementation guide
- [Laravel for SaaS: Multi-Tenant Data Safety - DEV](https://dev.to/kamruljpi/laravel-for-saas-how-to-keep-multi-tenant-data-safe-3o7d) -- Data isolation strategies
- [Field-Ready Multi-Tenant SaaS Guide](https://blog.greeden.me/en/2025/12/24/field-ready-complete-guide-designing-a-multi-tenant-saas-in-laravel-tenant-isolation-db-schema-row-domain-url-strategy-billing-authorization-auditing-performance-and-an-access/) -- DB/schema/row isolation comparison

### Offline-First
- [Offline-First Apps with Expo and Legend State](https://expo.dev/blog/offline-first-apps-with-expo-and-legend-state) -- Expo official guide
- [Local-First Architecture with Expo](https://docs.expo.dev/guides/local-first/) -- Official Expo docs
- [Offline-First Frontend Apps in 2025 - LogRocket](https://blog.logrocket.com/offline-first-frontend-apps-2025-indexeddb-sqlite/) -- IndexedDB and SQLite strategies
- [Building Offline-First Apps with React Query - DEV](https://dev.to/msaadullah/building-offline-first-apps-using-react-native-react-query-and-asyncstorage-1h4i) -- React Query + AsyncStorage pattern

### AI Guardrails and Validation
- [LLM Guardrails: Strategies & Best Practices 2025 - Leanware](https://www.leanware.co/insights/llm-guardrails) -- Comprehensive guardrails overview
- [LLM Guardrails Best Practices - Datadog](https://www.datadoghq.com/blog/llm-guardrails-best-practices/) -- Production deployment patterns
- [Guardrails AI on GitHub](https://github.com/guardrails-ai/guardrails) -- Output validation framework

### Psychometric Assessment
- [Career Values Scale Manual - Psychometrics](https://www.psychometrics.com/wp-content/uploads/2025/03/Career-Values-Scale-Manual-and-Users-Guide.pdf) -- Psychometric scale design
- [CareerExplorer Career Test](https://www.careerexplorer.com/career-test/) -- Modern AI-powered career assessment
- [O*NET Interest Profiler](https://www.onetcenter.org/IP.html) -- US DOL vocational assessment framework
- [Psychometric Models - ScienceDirect](https://www.sciencedirect.com/topics/social-sciences/psychometric-models) -- Academic overview of psychometric modeling
