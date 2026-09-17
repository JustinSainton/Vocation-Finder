<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\RunsOnTheConfiguredEngine;
use App\Models\Assessment;
use App\Models\SignalExtraction;
use App\Support\ConversationLocale;
use App\Support\DualTrack;
use App\Support\TaxonomyPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
#[Timeout(120)]
class VocationalAnalysis implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, RunsOnTheConfiguredEngine;

    public function __construct(
        protected Assessment $assessment,
        protected string $responseLocale = ConversationLocale::DEFAULT,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->baseInstructions()."\n\n".$this->categoryMapping();
    }

    /**
     * The 17 categories rendered from the governing taxonomy rather than
     * listed by name. The blueprint is explicit that a bare list cannot
     * constrain interpretation: the Short layer is the classification anchor.
     */
    protected function categoryMapping(): string
    {
        $anchors = TaxonomyPrompt::anchors();

        if ($anchors === '') {
            return '';
        }

        return <<<MAPPING
        ## Category Mapping

        Map patterns to these 17 vocational categories, scoring relevance 0-100.

        Each category below gives its core function, the desires, burdens and
        strengths that point toward it, literal phrases students use when this
        pathway is present, and the distortions it can take.

        Match against the evidence classes, not the category name. A student
        never says "I am oriented toward Healing & Care" — they say they could
        not stand seeing someone in pain.

        Distortions are diagnostic, not disqualifying. When a narrative matches
        a pathway's distorted form rather than its healthy one, the pathway may
        still be correct; note the immature expression rather than scoring it
        down.

        {$anchors}
        MAPPING;
    }

    protected function baseInstructions(): string
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
        $stringList = fn (string $description = "Specific quotes or references from the respondent's own answers") => $schema
            ->array()
            ->items($schema->string())
            ->description($description)
            ->required();

        return [
            'dimensions' => $schema->object([
                'service_orientation' => $schema->object([
                    'pattern' => $schema->string()->description('Primary service pattern identified')->required(),
                    'mode' => $schema->string()->enum(['direct_care', 'systemic', 'relational', 'technical', 'creative', 'educational'])->description('The primary mode in which they serve')->required(),
                    'evidence' => $stringList('Specific quotes or references from responses'),
                ])->description('How they naturally serve others')->required(),
                'problem_solving_draw' => $schema->object([
                    'primary_concern' => $schema->string()->description('What type of disorder or need compels them')->required(),
                    'scale' => $schema->string()->enum(['individual', 'organizational', 'community', 'societal'])->description('The scale at which the disorder that compels them operates')->required(),
                    'approach' => $schema->string()->enum(['direct_service', 'policy', 'innovation', 'education', 'creation', 'care'])->description('How they move toward that disorder')->required(),
                    'evidence' => $stringList(),
                ])->description('What disorder compels them. This reveals the DOMAIN of calling.')->required(),
                'energy_sources' => $schema->object([
                    'flow_activities' => $schema->string()->description('Activities that produce flow states')->required(),
                    'works_with' => $schema->string()->enum(['people', 'systems', 'ideas', 'tangible_things'])->description('What they work with when they are most engaged')->required(),
                    'mode' => $schema->string()->enum(['creating', 'organizing', 'discovering', 'caring', 'teaching', 'leading'])->description('The mode of work that produces flow')->required(),
                    'collaboration' => $schema->string()->enum(['solo', 'collaborative', 'leading_team'])->description('How they prefer to work alongside others')->required(),
                    'evidence' => $stringList(),
                ])->description('Where gifts actually lie, based on flow states and engagement. This reveals the METHOD of work.')->required(),
                'values_decision_making' => $schema->object([
                    'primary_driver' => $schema->string()->description('What drives their decisions when values conflict')->required(),
                    'risk_orientation' => $schema->string()->enum(['risk_taking', 'security_seeking', 'calculated', 'faith_driven'])->description('How they weigh risk against security')->required(),
                    'theological_maturity' => $schema->string()->enum(['emerging', 'developing', 'mature'])->description('Maturity of their thinking about calling. Never surfaced to the respondent.')->required(),
                    'evidence' => $stringList(),
                ])->description('How they weigh competing goods')->required(),
                'response_to_obstacles' => $schema->object([
                    'interpretation' => $schema->string()->description('How they interpret limitations and setbacks')->required(),
                    'providence_awareness' => $schema->string()->enum(['strong', 'moderate', 'emerging', 'not_expressed'])->description('How strongly they read circumstances as providential')->required(),
                    'resilience_style' => $schema->string()->enum(['adaptive', 'persevering', 'reflective', 'resourceful'])->description('How they carry on through obstacles')->required(),
                    'evidence' => $stringList(),
                ])->description('How they interpret limitations and closed doors')->required(),
                'vision_legacy' => $schema->object([
                    'scope' => $schema->string()->enum(['local', 'regional', 'broad', 'generational'])->description('The reach of the impact they hope for')->required(),
                    'focus' => $schema->string()->enum(['individuals', 'systems', 'culture', 'ideas', 'communities'])->description('What they most want to affect')->required(),
                    'contribution_type' => $schema->string()->description('What "making a difference" means to them')->required(),
                    'evidence' => $stringList(),
                ])->description('The scope of impact they desire')->required(),
            ])->description('The six dimensions of vocational calling, each grounded in the respondent\'s own words')->required(),
            'category_scores' => $schema
                ->array()
                ->items($schema->object([
                    /*
                     | Closed, like the signal references below it. The
                     | seventeen names are matched downstream by normalising
                     | and comparing strings, so a model that returns
                     | "Creative Arts and Design" for "Creating & Building"
                     | produces a row that resolves to nothing and is carried
                     | forward anyway. The enum removes the spelling question.
                     */
                    'category' => $schema->string()->enum(TaxonomyPrompt::names())->description('One of the 17 vocational category names')->required(),
                    'score' => $schema->integer()->min(0)->max(100)->description('Relevance score 0-100')->required(),
                    'rationale' => $schema->string()->description('Brief explanation for the score')->required(),
                    'evidence' => $schema
                        ->array()
                        ->items($this->signalReference($schema))
                        ->description('Signal references (for example "S3") from the Detected Signals section that bear on this category. Cite only references that appear there. Empty if nothing they said bears on it.')
                        ->required(),
                ]))
                ->min(17)
                ->description('Scored relevance for each of the 17 vocational categories')
                ->required(),
            'primary_domain' => $schema->string()->description('The primary vocational domain (e.g., "designing and building structures that serve communities")')->required(),
            'mode_of_work' => $schema->string()->description('How they would work in that domain (e.g., "entrepreneurial ownership", "collaborative research")')->required(),
            'secondary_orientation' => $schema->string()->description('Secondary calling dimension (e.g., "leadership and team development")')->required(),
            'ministry_connection' => $schema->string()->description('How their vocation connects to ministry and service to neighbor — grounded in their specific responses')->required(),
        ];
    }

    /**
     * The Layer 4 signals for this assessment, rendered for the mapping pass.
     *
     * Every span here has already been verified against the answer it was
     * taken from, so this block is evidence rather than another model's
     * opinion. It is additive: when Layer 4 produced nothing — because it
     * failed, or because the assessment predates it — the prompt is exactly
     * what it was before and the analysis still works from the raw responses.
     */
    /**
     * The references this assessment's citations may name, as an enum.
     *
     * The signal references are a **closed vocabulary** — S1 through Sn for
     * exactly the signals rendered into this prompt, and known before the
     * model is ever called. Asking for a free string and discarding what comes
     * back is asking the model for a value we already have.
     *
     * Stated as an enum, the provider compiles it into the decoding grammar
     * and an invented reference stops being possible rather than being caught
     * afterwards. The instruction telling the model not to invent one is
     * clear, and an instruction is not a constraint.
     *
     * Falls back to a plain string when Layer 4 produced nothing, because an
     * empty enum is not a schema — and in that case every citation is
     * unresolvable anyway, which {@see DualTrack} already handles.
     */
    protected function signalReference(JsonSchema $schema): mixed
    {
        $references = array_keys(DualTrack::index($this->assessment->signalExtractions));

        return $references === []
            ? $schema->string()
            : $schema->string()->enum($references);
    }

    protected function detectedSignals(): string
    {
        $signals = $this->assessment->signalExtractions;

        if ($signals->isEmpty()) {
            return '';
        }

        $rendered = collect(DualTrack::index($signals))
            // preserveKeys, or the S-references are replaced by positional
            // integers and every citation the model makes becomes an
            // invention that DualTrack correctly discards.
            ->groupBy(fn (SignalExtraction $signal) => $signal->track->label(), preserveKeys: true)
            ->map(function ($group, string $track) {
                $lines = $group->map(fn (SignalExtraction $signal, string $ref) => sprintf(
                    '- %s [%s] %s — "%s"',
                    $ref,
                    $signal->type->label(),
                    $signal->content,
                    $signal->verbatim,
                ))->values();

                return "### {$track}\n".$lines->implode("\n");
            })
            ->implode("\n\n");

        return <<<SIGNALS


        ## Detected Signals

        These were extracted from the responses above and each quote has been
        verified as the respondent's own words. Weigh them as evidence.

        Aspiration and demonstrated evidence are listed separately and must be
        weighed separately. What someone has actually done carries more weight
        than what they say they want; the gap between the two is not a
        contradiction to resolve but the shape of their development.

        Each signal carries a reference like S4. When you score a category,
        list in its `evidence` the references that actually bear on it. Cite
        only references that appear below — a reference that is not here will
        be discarded, and the category will read as unevidenced. Citing
        nothing is the honest answer for a category nothing they said touches.

        {$rendered}
        SIGNALS;
    }

    public function buildPrompt(): string
    {
        $locale = ConversationLocale::normalize($this->responseLocale ?: $this->assessment->locale);
        $languageName = ConversationLocale::displayName($locale);
        $answers = $this->assessment->answers()
            ->with('question.category', 'question.translations')
            ->orderBy('id')
            ->get();

        $questionCount = $answers->count();
        $categoryCount = $answers->map(fn ($a) => $a->question->category?->name ?? 'Unknown')->unique()->count();

        $formatted = $answers->map(function ($answer) use ($locale) {
            $category = $answer->question->category->name ?? 'Unknown';
            $question = $answer->question->localizedQuestionText($locale);
            $response = $answer->response_text ?: $answer->audio_transcript;

            return "**[{$category}] Q: {$question}**\nResponse: {$response}";
        })->join("\n\n---\n\n");

        $signals = $this->detectedSignals();

        return <<<PROMPT
Analyze the following vocational discernment assessment responses. The respondent answered {$questionCount} questions across {$categoryCount} categories designed to reveal their vocational calling.

## Response Language

Write all descriptive string values, rationales, and the ministry connection in {$languageName} ({$locale}).
Keep the JSON field names exactly as defined by the schema.

## Assessment Responses

{$formatted}
{$signals}

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
