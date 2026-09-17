<?php

namespace Tests\Feature;

use App\Ai\Agents\JobClassifierAgent;
use App\Ai\Agents\VocationalAnalysis;
use App\Models\Assessment;
use App\Models\VocationalCategory;
use App\Support\TaxonomyPrompt;
use Database\Seeders\VocationalCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blueprint roadmap item 0.7 — proves the governing taxonomy actually reaches
 * the model. Seeding rich category content is worthless if the prompt still
 * ships a bare list of names, which is what it did before this change.
 */
class TaxonomyPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VocationalCategorySeeder::class);
    }

    protected function assessment(): Assessment
    {
        return Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);
    }

    public function test_anchors_render_every_category_with_its_core_function(): void
    {
        $anchors = TaxonomyPrompt::anchors();

        foreach (VocationalCategory::all() as $category) {
            $this->assertStringContainsString($category->name, $anchors);
            $this->assertStringContainsString($category->core_function, $anchors);
        }
    }

    public function test_anchors_include_evidence_classes_and_distortions(): void
    {
        $anchors = TaxonomyPrompt::anchors();

        $this->assertStringContainsString('Desires:', $anchors);
        $this->assertStringContainsString('Burdens:', $anchors);
        $this->assertStringContainsString('Strengths:', $anchors);
        $this->assertStringContainsString('Distortions:', $anchors);
        $this->assertStringContainsString('Literal signal phrases:', $anchors);
    }

    public function test_anchors_exclude_the_expanded_interpretive_layer(): void
    {
        // The short layer is the classification anchor. Rendering every
        // expanded layer here would bury it and inflate the prompt.
        $anchors = TaxonomyPrompt::anchors();
        $expanded = VocationalCategory::where('slug', 'healing-care')
            ->value('taxonomy_profile')['q1']['expanded'];

        $this->assertStringNotContainsString($expanded, $anchors);
    }

    public function test_disambiguation_returns_expanded_layer_and_differentiating_questions(): void
    {
        $prompt = TaxonomyPrompt::disambiguation(['healing-care', 'nourishing-hospitality']);

        $this->assertStringContainsString('Healing & Care', $prompt);
        $this->assertStringContainsString('Nourishing & Hospitality', $prompt);
        $this->assertStringContainsString('Ask yourself:', $prompt);
        $this->assertStringNotContainsString('Law & Policy', $prompt);
    }

    public function test_disambiguation_is_empty_for_no_candidates(): void
    {
        $this->assertSame('', TaxonomyPrompt::disambiguation([]));
        $this->assertSame('', TaxonomyPrompt::disambiguation(['not-a-real-category']));
    }

    public function test_summary_sentence_returns_the_verbatim_q10(): void
    {
        $this->assertSame(
            'You make rooms where people remember they belong.',
            TaxonomyPrompt::summarySentence('nourishing-hospitality'),
        );

        $this->assertNull(TaxonomyPrompt::summarySentence('not-a-real-category'));
    }

    public function test_analysis_agent_instructions_carry_the_governing_taxonomy(): void
    {
        $assessment = $this->assessment();

        $instructions = (string) (new VocationalAnalysis($assessment))->instructions();

        $this->assertStringContainsString('## Category Mapping', $instructions);
        $this->assertStringContainsString('Distortions are diagnostic, not disqualifying', $instructions);

        foreach (VocationalCategory::all() as $category) {
            $this->assertStringContainsString(
                $category->core_function,
                $instructions,
                "Instructions omit the core function for '{$category->slug}'.",
            );
        }
    }

    /**
     * The job classifier used to carry its own hand-written copy of the
     * seventeen categories, with parentheticals nobody had checked against
     * the taxonomy in months. A copy of a list is a list that will still say
     * seventeen things after the taxonomy says eighteen — and the schema
     * already constrains the slug enum from the data file, so the prompt and
     * the schema were free to disagree about what the categories *mean*.
     */
    public function test_the_job_classifier_names_categories_from_the_taxonomy(): void
    {
        $instructions = (string) (new JobClassifierAgent('Staff Nurse', 'Ward nursing.'))->instructions();

        $entries = require database_path('seeders/data/vocational_taxonomy.php');

        foreach ($entries as $entry) {
            $this->assertStringContainsString($entry['slug'], $instructions);
            $this->assertStringContainsString(
                $entry['core_function'],
                $instructions,
                "The classifier describes '{$entry['slug']}' in words the taxonomy does not use.",
            );
        }

        $this->assertCount(
            count($entries),
            TaxonomyPrompt::slugs(),
            'The enum the schema constrains and the list the prompt renders come from the same file.',
        );
    }

    public function test_analysis_instructions_retain_the_theological_framework(): void
    {
        $assessment = $this->assessment();

        $instructions = (string) (new VocationalAnalysis($assessment))->instructions();

        $this->assertStringContainsString('Theological Framework', $instructions);
        $this->assertStringContainsString('Critical Rules', $instructions);
    }
}
