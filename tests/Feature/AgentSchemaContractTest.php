<?php

namespace Tests\Feature;

use App\Ai\Agents\CategoryDisambiguation;
use App\Ai\Agents\ConversationAgent;
use App\Ai\Agents\CurriculumCuration;
use App\Ai\Agents\JobClassifierAgent;
use App\Ai\Agents\ResumeParserAgent;
use App\Ai\Agents\ResumeQualityAgent;
use App\Ai\Agents\ResumeWriterAgent;
use App\Ai\Agents\SignalDetection;
use App\Ai\Agents\SyllabusParser;
use App\Ai\Agents\VocationalAnalysis;
use App\Ai\Agents\VoiceAnalyzerAgent;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\SignalExtraction;
use App\Models\VocationalCategory;
use App\Support\DualTrack;
use App\Support\TaxonomyPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the wire format of agent output schemas.
 *
 * Providers that constrain decoding against the schema (Ollama compiles it to
 * a GBNF grammar) will emit partial objects when "required" is absent, because
 * the grammar then makes every key optional. Local models fail this hard;
 * hosted models fail it intermittently. Both are silent.
 */
class AgentSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function serialize(array $schema): array
    {
        return (new ObjectSchema($schema))->toArray();
    }

    public function test_conversation_agent_schema_requires_every_field(): void
    {
        $agent = new ConversationAgent('Why does that matter to you?', 'I dunno.', []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            ['is_sufficient', 'follow_up_question', 'synthesized_answer', 'reasoning'],
            $serialized['required'] ?? [],
        );
    }

    public function test_conversation_agent_optional_fields_accept_null_while_staying_required(): void
    {
        $agent = new ConversationAgent('Why does that matter to you?', 'I dunno.', []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(['string', 'null'], $serialized['properties']['follow_up_question']['type']);
        $this->assertSame(['string', 'null'], $serialized['properties']['synthesized_answer']['type']);
        $this->assertSame('boolean', $serialized['properties']['is_sufficient']['type']);
        $this->assertSame('string', $serialized['properties']['reasoning']['type']);
    }

    public function test_conversation_agent_schema_carries_field_descriptions(): void
    {
        $agent = new ConversationAgent('Why does that matter to you?', 'I dunno.', []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        foreach (['is_sufficient', 'follow_up_question', 'synthesized_answer', 'reasoning'] as $field) {
            $this->assertNotEmpty(
                $serialized['properties'][$field]['description'] ?? '',
                "The {$field} property lost its description. Descriptions must be set with ->description(), "
                .'not passed as an argument to the factory method, which discards them silently.'
            );
        }
    }

    /**
     * The citation vocabulary is closed, so the schema closes it.
     *
     * A signal reference is S1..Sn for exactly the signals rendered into this
     * prompt. Left as a free string, a model cites the question text back, or
     * invents an S9, and {@see DualTrack} correctly discards all
     * of it — leaving a portrait where every layer worked and nothing is
     * evidenced. Declared as an enum, Ollama compiles it into the decoding
     * grammar and the invention is not available to make.
     */
    public function test_vocational_analysis_constrains_citations_to_the_signals_it_showed(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        // Question has no factory; the seeder is the only source of one.
        $this->seed();

        $question = Question::orderBy('sort_order')->firstOrFail();

        $answer = Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => 'I sat with her until she stopped crying.',
        ]);

        foreach ([SignalType::Skill, SignalType::Burden] as $position => $type) {
            SignalExtraction::create([
                'assessment_id' => $assessment->id,
                'answer_id' => $answer->id,
                'type' => $type,
                'track' => SignalTrack::Demonstrated,
                'content' => 'Stayed with someone in distress.',
                'verbatim' => 'I sat with her until she stopped crying.',
                'sort_order' => $position,
            ]);
        }

        $serialized = $this->serialize((new VocationalAnalysis($assessment->fresh()))->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            ['S1', 'S2'],
            $serialized['properties']['category_scores']['items']['properties']['evidence']['items']['enum'] ?? null,
            'A model may cite anything it likes, and everything it invents is thrown away in silence.',
        );
    }

    /**
     * An empty enum is not a schema. When Layer 4 produced nothing there is no
     * vocabulary to close, and every citation is unresolvable regardless.
     */
    public function test_vocational_analysis_leaves_citations_open_when_there_are_no_signals(): void
    {
        $serialized = $this->serialize((new VocationalAnalysis(new Assessment))->schema(new JsonSchemaTypeFactory));

        $evidence = $serialized['properties']['category_scores']['items']['properties']['evidence']['items'];

        $this->assertSame('string', $evidence['type']);
        $this->assertArrayNotHasKey('enum', $evidence);
    }

    /**
     * The other closed vocabulary in the same schema.
     *
     * The seventeen names are matched downstream by lowercasing and swapping
     * "&" for "and" — a model that answers "Creative Arts and Design" where
     * the taxonomy says "Creating & Building" produces a row that resolves to
     * nothing and is carried forward regardless. Left to a prompt this is a
     * spelling question; declared as an enum it is not a question.
     */
    public function test_vocational_analysis_constrains_categories_to_the_taxonomy(): void
    {
        $serialized = $this->serialize((new VocationalAnalysis(new Assessment))->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            TaxonomyPrompt::names(),
            $serialized['properties']['category_scores']['items']['properties']['category']['enum'] ?? null,
        );
    }

    /**
     * The names the schema closes over must be the names that exist. The data
     * file builds the schema without a database; the seeder builds the
     * database. If they disagree the model is constrained to say things the
     * taxonomy does not contain.
     */
    public function test_the_taxonomy_names_agree_with_the_seeded_categories(): void
    {
        $this->seed();

        $this->assertSame(
            TaxonomyPrompt::names(),
            VocationalCategory::query()->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    /**
     * The narrowest closed vocabulary in the engine: the pathways actually
     * competing. `applyResolvedRanking` re-ranks by matching these names
     * against the analysis rows, so a name from outside the pair re-ranks
     * nothing and the disambiguation pass silently does not happen.
     */
    public function test_disambiguation_constrains_the_ranking_to_the_competing_pathways(): void
    {
        $agent = new CategoryDisambiguation(['healing-care', 'creating-building'], []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            ['Healing & Care', 'Creating & Building'],
            $serialized['properties']['ranking']['items']['properties']['category']['enum'] ?? null,
        );
    }

    /**
     * An empty enum is not a schema, so an unrecognised pair falls back to the
     * whole taxonomy rather than to nothing.
     */
    public function test_disambiguation_falls_back_to_the_taxonomy_when_the_pair_is_unrecognised(): void
    {
        $agent = new CategoryDisambiguation(['not-a-pathway'], []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            TaxonomyPrompt::names(),
            $serialized['properties']['ranking']['items']['properties']['category']['enum'] ?? null,
        );
    }

    public function test_vocational_analysis_schema_requires_every_field(): void
    {
        $assessment = new Assessment;
        $serialized = $this->serialize((new VocationalAnalysis($assessment))->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            ['dimensions', 'category_scores', 'primary_domain', 'mode_of_work', 'secondary_orientation', 'ministry_connection'],
            $serialized['required'] ?? [],
        );

        $dimensions = $serialized['properties']['dimensions'];
        $this->assertSame(array_keys($dimensions['properties']), $dimensions['required'] ?? []);

        foreach ($dimensions['properties'] as $name => $dimension) {
            $this->assertSame(
                array_keys($dimension['properties']),
                $dimension['required'] ?? [],
                "The {$name} dimension does not require all of its own properties."
            );
        }

        $this->assertSame(
            // `evidence` is required so a local model declares the field even
            // when it cites nothing; an omitted array and an empty one must
            // not be the same bug. See DualTrack.
            ['category', 'score', 'rationale', 'evidence'],
            $serialized['properties']['category_scores']['items']['required'] ?? [],
        );
    }

    public function test_category_disambiguation_schema_requires_every_field(): void
    {
        $agent = new CategoryDisambiguation(['healing-and-care', 'teaching-and-formation'], []);

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            ['resolved', 'ranking', 'differentiator', 'distortion_noted', 'insufficient_evidence_reason'],
            $serialized['required'] ?? [],
        );

        $this->assertSame(
            ['category', 'score', 'rationale'],
            $serialized['properties']['ranking']['items']['required'] ?? [],
        );
    }

    /**
     * Every agent in the application, with constructor arguments good enough
     * to build its schema. Registered here on purpose: a new structured agent
     * should fail this suite until it is added and proven to hold the
     * contract.
     *
     * @return array<string, HasStructuredOutput>
     */
    public static function structuredAgents(): array
    {
        return [
            'ConversationAgent' => [new ConversationAgent('Q?', 'A.', [])],
            'VocationalAnalysis' => [new VocationalAnalysis(new Assessment)],
            'CategoryDisambiguation' => [new CategoryDisambiguation(['healing-and-care'], [])],
            'CurriculumCuration' => [new CurriculumCuration([], [])],
            'ResumeParserAgent' => [new ResumeParserAgent('resume text')],
            'ResumeWriterAgent' => [new ResumeWriterAgent([], [], [])],
            'ResumeQualityAgent' => [new ResumeQualityAgent('resume text', 'Nurse')],
            'JobClassifierAgent' => [new JobClassifierAgent('Nurse', 'Cares for patients.')],
            'VoiceAnalyzerAgent' => [new VoiceAnalyzerAgent(['a writing sample'])],
            'SignalDetection' => [new SignalDetection([])],
            'SyllabusParser' => [new SyllabusParser('syllabus text')],
        ];
    }

    /**
     * Five agents shipped a schema that raised
     * "Unknown named parameter $items" the moment it was built, because the
     * factory methods declare no parameters. Building every schema is the
     * cheapest possible guard against that returning.
     */
    #[DataProvider('structuredAgents')]
    public function test_every_agent_can_build_its_schema(HasStructuredOutput $agent): void
    {
        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame('object', $serialized['type']);
        $this->assertNotEmpty($serialized['properties'] ?? []);
    }

    /**
     * Walks every object level, including array item objects, and asserts the
     * required list names all of that level's properties.
     */
    #[DataProvider('structuredAgents')]
    public function test_every_agent_requires_every_field_at_every_level(HasStructuredOutput $agent): void
    {
        $this->assertFullyRequired(
            $this->serialize($agent->schema(new JsonSchemaTypeFactory)),
            $agent::class,
        );
    }

    #[DataProvider('structuredAgents')]
    public function test_every_agent_describes_every_field(HasStructuredOutput $agent): void
    {
        $this->assertDescribed(
            $this->serialize($agent->schema(new JsonSchemaTypeFactory)),
            $agent::class,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function assertFullyRequired(array $node, string $agent, string $path = '$'): void
    {
        if (($node['properties'] ?? null) !== null) {
            $this->assertSame(
                array_keys($node['properties']),
                $node['required'] ?? [],
                "{$agent}: the object at {$path} does not require every one of its properties. "
                .'Providers that constrain decoding against the schema treat an absent or partial '
                .'"required" list as "these keys are optional" and will emit incomplete objects.'
            );

            foreach ($node['properties'] as $name => $child) {
                $this->assertFullyRequired($child, $agent, "{$path}.{$name}");
            }
        }

        if (($node['items'] ?? null) !== null) {
            $this->assertFullyRequired($node['items'], $agent, "{$path}[]");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function assertDescribed(array $node, string $agent, string $path = '$'): void
    {
        foreach ($node['properties'] ?? [] as $name => $child) {
            $this->assertNotEmpty(
                $child['description'] ?? '',
                "{$agent}: the property at {$path}.{$name} has no description. Descriptions must be "
                .'set with ->description(); passing one to the factory method discards it silently.'
            );

            $this->assertDescribed($child, $agent, "{$path}.{$name}");
        }

        if (($node['items'] ?? null) !== null) {
            $this->assertDescribed($node['items'], $agent, "{$path}[]");
        }
    }

    /**
     * The classifier may only return slugs that exist in the governing
     * taxonomy. Without the enum the model is free to invent a slug that
     * looks plausible and matches nothing downstream.
     */
    public function test_job_classifier_can_only_return_real_category_slugs(): void
    {
        $agent = new JobClassifierAgent('Nurse', 'Cares for patients.');

        $serialized = $this->serialize($agent->schema(new JsonSchemaTypeFactory));

        $this->assertSame(
            TaxonomyPrompt::slugs(),
            $serialized['properties']['categories']['items']['properties']['slug']['enum'] ?? [],
        );

        $this->assertCount(17, TaxonomyPrompt::slugs());
    }

    /**
     * The categories listed in the classifier prompt are a second copy of the
     * taxonomy. They must not drift from the governing one.
     */
    public function test_job_classifier_prompt_lists_exactly_the_governing_slugs(): void
    {
        $instructions = (string) (new JobClassifierAgent('Nurse', 'Cares.'))->instructions();

        preg_match_all('/^- ([a-z-]+): /m', $instructions, $matches);

        $this->assertSame(
            TaxonomyPrompt::slugs(),
            $matches[1],
            'The category list in JobClassifierAgent has drifted from the governing taxonomy.'
        );
    }

    /**
     * Totals and pass/fail are arithmetic. A model asked for arithmetic gets
     * it wrong, so they must not be in the schema at all.
     */
    public function test_resume_quality_does_not_ask_the_model_for_derived_values(): void
    {
        $agent = new ResumeQualityAgent('resume', 'Nurse');

        $properties = $this->serialize($agent->schema(new JsonSchemaTypeFactory))['properties'];

        $this->assertArrayNotHasKey('total_score', $properties);
        $this->assertArrayNotHasKey('passes_quality_gate', $properties);
    }

    public function test_resume_quality_derives_the_total_and_the_gate_from_the_bands(): void
    {
        $normalized = ResumeQualityAgent::normalize([
            'specificity_score' => 20,
            'authenticity_score' => 20,
            'ats_score' => 20,
            'alignment_score' => 15,
        ]);

        $this->assertSame(75.0, $normalized['total_score']);
        $this->assertTrue($normalized['passes_quality_gate']);
    }

    /**
     * The exact contradiction llama3.2:3b produced: bands summing to 45, a
     * reported total of 40, and the gate set to true.
     */
    public function test_resume_quality_overrides_a_contradictory_model_result(): void
    {
        $normalized = ResumeQualityAgent::normalize([
            'specificity_score' => 10,
            'authenticity_score' => 15,
            'ats_score' => 15,
            'alignment_score' => 5,
            'total_score' => 40,
            'passes_quality_gate' => true,
        ]);

        $this->assertSame(45.0, $normalized['total_score']);
        $this->assertFalse($normalized['passes_quality_gate']);
    }

    public function test_resume_quality_clamps_out_of_range_bands(): void
    {
        $normalized = ResumeQualityAgent::normalize([
            'specificity_score' => 900,
            'authenticity_score' => -40,
            'ats_score' => 25,
            'alignment_score' => 25,
        ]);

        $this->assertSame(25.0, $normalized['specificity_score']);
        $this->assertSame(0.0, $normalized['authenticity_score']);
        $this->assertSame(75.0, $normalized['total_score']);
    }

    public function test_resume_quality_treats_missing_bands_as_zero(): void
    {
        $normalized = ResumeQualityAgent::normalize([]);

        $this->assertSame(0.0, $normalized['total_score']);
        $this->assertFalse($normalized['passes_quality_gate']);
    }

    /**
     * The factory methods take no arguments. A positional argument is silently
     * discarded by PHP; a named one throws. Either way the description is lost,
     * so no agent may pass one.
     */
    public function test_schema_factory_methods_reject_inline_descriptions(): void
    {
        $factory = new JsonSchemaTypeFactory;

        $this->assertSame([], (new \ReflectionMethod($factory, 'string'))->getParameters());
        $this->assertSame([], (new \ReflectionMethod($factory, 'boolean'))->getParameters());
        $this->assertSame([], (new \ReflectionMethod($factory, 'array'))->getParameters());
    }
}
