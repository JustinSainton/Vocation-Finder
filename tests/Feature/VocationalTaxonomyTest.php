<?php

namespace Tests\Feature;

use App\Models\VocationalCategory;
use Database\Seeders\VocationalCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards blueprint roadmap item 0.7 — the taxonomy that governs the AI.
 *
 * These are integrity tests, not coverage tests. The taxonomy is transcribed
 * governed content, so the failure mode worth defending against is silent
 * drift: a sentence paraphrased, a distortion dropped, an adjacency broken.
 */
class VocationalTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VocationalCategorySeeder::class);
    }

    public function test_it_seeds_all_seventeen_categories_with_governing_taxonomy(): void
    {
        $categories = VocationalCategory::all();

        $this->assertCount(17, $categories);

        foreach ($categories as $category) {
            $this->assertTrue(
                $category->hasGoverningTaxonomy(),
                "Category '{$category->slug}' is missing governing taxonomy content.",
            );
        }
    }

    public function test_every_category_carries_all_ten_blueprint_questions(): void
    {
        foreach (VocationalCategory::all() as $category) {
            $profile = $category->taxonomy_profile;

            foreach (range(1, 10) as $question) {
                $this->assertArrayHasKey(
                    "q{$question}",
                    $profile,
                    "Category '{$category->slug}' is missing Q{$question}.",
                );
            }

            foreach (range(1, 9) as $question) {
                $this->assertNotEmpty($profile["q{$question}"]['short'] ?? null);
                $this->assertNotEmpty($profile["q{$question}"]['expanded'] ?? null);
            }
        }
    }

    public function test_q10_summary_sentences_are_preserved_verbatim(): void
    {
        // Pre-approved by the founder and surfaced to students exactly as written.
        // If this test fails, the sentence was paraphrased — restore it, do not
        // update the expectation.
        $verbatim = [
            'healing-care' => 'You are drawn to what is wounded because you carry the instinct to make it whole.',
            'teaching-formation' => "You come alive at the moment understanding dawns in someone else's eyes.",
            'discovering-innovating' => 'The unanswered question pulls you the way gravity pulls water.',
            'nourishing-hospitality' => 'You make rooms where people remember they belong.',
            'advocating-supporting' => 'You cannot walk past someone the system walked over.',
            'pastoral-missionary' => 'You feel the weight of souls the way others feel the weight of deadlines.',
        ];

        foreach ($verbatim as $slug => $sentence) {
            $this->assertSame(
                $sentence,
                VocationalCategory::where('slug', $slug)->value('summary_sentence'),
            );
        }
    }

    public function test_every_summary_sentence_is_a_complete_second_person_sentence(): void
    {
        foreach (VocationalCategory::all() as $category) {
            $sentence = $category->summary_sentence;

            $this->assertStringEndsWith('.', $sentence, "Q10 for '{$category->slug}' is not a complete sentence.");
            $this->assertMatchesRegularExpression(
                '/\b(you|your)\b/i',
                $sentence,
                "Q10 for '{$category->slug}' must address the student directly.",
            );
        }
    }

    public function test_adjacent_categories_resolve_to_real_categories(): void
    {
        foreach (VocationalCategory::all() as $category) {
            $adjacent = $category->adjacentCategories();

            $this->assertNotEmpty(
                $adjacent,
                "Category '{$category->slug}' declares no adjacent categories; the engine cannot disambiguate it.",
            );

            $this->assertCount(
                count($category->adjacent_categories['slugs']),
                $adjacent,
                "Category '{$category->slug}' references an adjacent slug that does not exist.",
            );

            $this->assertNotContains(
                $category->slug,
                $adjacent->pluck('slug')->all(),
                "Category '{$category->slug}' lists itself as adjacent.",
            );
        }
    }

    public function test_every_category_provides_differentiating_questions(): void
    {
        foreach (VocationalCategory::all() as $category) {
            $questions = $category->differentiatingQuestions();

            $this->assertNotEmpty(
                $questions,
                "Category '{$category->slug}' has no differentiating questions; competing pathways cannot be resolved.",
            );

            foreach ($questions as $question) {
                $this->assertStringEndsWith('?', $question);
            }
        }
    }

    public function test_every_category_declares_distortions(): void
    {
        // Distortions are diagnostic, not disqualifying: a narrative matching a
        // distortion is still evidence for the pathway. The engine cannot make
        // that call for a category that declares none.
        foreach (VocationalCategory::all() as $category) {
            $this->assertNotEmpty(
                $category->distortions,
                "Category '{$category->slug}' declares no distortions.",
            );
        }
    }

    public function test_signal_fingerprints_carry_every_evidence_class(): void
    {
        foreach (VocationalCategory::all() as $category) {
            foreach (['desires', 'burdens', 'strengths', 'environments'] as $class) {
                $this->assertNotEmpty(
                    $category->signal_fingerprint[$class] ?? [],
                    "Category '{$category->slug}' has no '{$class}' signals.",
                );
            }
        }
    }

    public function test_literal_signal_phrases_are_available_for_matching(): void
    {
        $withPhrases = VocationalCategory::all()
            ->filter(fn (VocationalCategory $category) => $category->literalSignalPhrases() !== []);

        $this->assertGreaterThanOrEqual(
            10,
            $withPhrases->count(),
            'Too few categories carry literal signal phrases for narrative matching.',
        );
    }

    public function test_seeding_twice_does_not_duplicate_categories(): void
    {
        $this->seed(VocationalCategorySeeder::class);

        $this->assertCount(17, VocationalCategory::all());
        $this->assertSame(17, VocationalCategory::distinct()->count('slug'));
    }
}
