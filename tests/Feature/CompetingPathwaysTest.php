<?php

namespace Tests\Feature;

use App\Support\CompetingPathways;
use Database\Seeders\VocationalCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the trigger for the second interpretive pass.
 *
 * This logic decides when to spend an extra AI call. Firing too eagerly wastes
 * money on every assessment; firing too rarely means the engine silently
 * guesses between pathways it cannot actually distinguish.
 */
class CompetingPathwaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VocationalCategorySeeder::class);
    }

    /**
     * @param  array<string, int>  $scores
     * @return array<int, array{category: string, score: int}>
     */
    protected function scores(array $scores): array
    {
        return collect($scores)
            ->map(fn (int $score, string $category) => ['category' => $category, 'score' => $score])
            ->values()
            ->all();
    }

    public function test_a_clear_leader_does_not_trigger_a_second_pass(): void
    {
        $competing = CompetingPathways::detect($this->scores([
            'Healing & Care' => 88,
            'Nourishing & Hospitality' => 61,
            'Teaching & Formation' => 54,
        ]));

        $this->assertSame([], $competing);
    }

    public function test_clustered_leaders_are_detected(): void
    {
        $competing = CompetingPathways::detect($this->scores([
            'Healing & Care' => 82,
            'Nourishing & Hospitality' => 79,
            'Advocating & Supporting' => 77,
            'Teaching & Formation' => 50,
        ]));

        $this->assertSame(
            ['healing-care', 'nourishing-hospitality', 'advocating-supporting'],
            $competing,
        );
    }

    public function test_weak_scores_tying_do_not_trigger_a_second_pass(): void
    {
        // Three categories tied at 40 is a thin assessment, not a genuine
        // competition between pathways. Low-confidence mode handles this.
        $competing = CompetingPathways::detect($this->scores([
            'Healing & Care' => 42,
            'Nourishing & Hospitality' => 40,
            'Teaching & Formation' => 39,
        ]));

        $this->assertSame([], $competing);
    }

    public function test_candidates_below_the_floor_are_excluded_from_a_real_cluster(): void
    {
        $competing = CompetingPathways::detect($this->scores([
            'Healing & Care' => 60,
            'Nourishing & Hospitality' => 57,
            'Teaching & Formation' => 53,
        ]));

        $this->assertSame(['healing-care', 'nourishing-hospitality'], $competing);
    }

    public function test_the_candidate_list_is_capped(): void
    {
        $competing = CompetingPathways::detect($this->scores([
            'Healing & Care' => 80,
            'Nourishing & Hospitality' => 79,
            'Advocating & Supporting' => 78,
            'Teaching & Formation' => 77,
            'Pastoral & Missionary Work' => 76,
            'Leadership & Management' => 75,
        ]));

        $this->assertCount(CompetingPathways::MAX_CANDIDATES, $competing);
    }

    public function test_category_names_are_matched_despite_inflection(): void
    {
        // The model returns names, not slugs, and will not always match our
        // punctuation. A missed match would silently drop the score.
        $competing = CompetingPathways::detect([
            ['category' => 'Healing and Care', 'score' => 82],
            ['category' => 'nourishing & hospitality', 'score' => 80],
            ['category' => 'Law & Policy', 'score' => 30],
        ]);

        $this->assertSame(['healing-care', 'nourishing-hospitality'], $competing);
    }

    public function test_unknown_categories_are_ignored_rather_than_crashing(): void
    {
        $competing = CompetingPathways::detect([
            ['category' => 'Underwater Basket Weaving', 'score' => 99],
            ['category' => 'Healing & Care', 'score' => 80],
            ['category' => 'Nourishing & Hospitality', 'score' => 78],
        ]);

        $this->assertSame(['healing-care', 'nourishing-hospitality'], $competing);
    }

    public function test_empty_and_single_score_inputs_are_safe(): void
    {
        $this->assertSame([], CompetingPathways::detect([]));
        $this->assertSame([], CompetingPathways::detect($this->scores(['Healing & Care' => 90])));
    }

    public function test_adjacency_is_detected_for_categories_the_blueprint_pairs(): void
    {
        $this->assertTrue(
            CompetingPathways::areAdjacent(['healing-care', 'nourishing-hospitality']),
        );
    }

    public function test_non_adjacent_competitors_are_reported_as_such(): void
    {
        $this->assertFalse(
            CompetingPathways::areAdjacent(['finance-economics', 'protecting-defending']),
        );

        $this->assertFalse(CompetingPathways::areAdjacent(['healing-care']));
    }
}
