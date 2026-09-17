<?php

namespace Tests\Feature;

use App\Ai\Agents\NarrativeSynthesis;
use App\Enums\ConfidenceLevel;
use App\Support\CompetingPathways;
use App\Support\ConfidenceCalculator;
use Tests\TestCase;

/**
 * Blueprint 8.6 confidence levels.
 *
 * The governing constraint is that the system "must know when not to
 * over-speak": a high score on thin evidence is not a strong signal, and the
 * engine must be able to say so and say why.
 */
class ConfidenceLevelTest extends TestCase
{
    /**
     * @return list<string>
     */
    protected function answers(int $count, int $words): array
    {
        return array_fill(0, $count, trim(str_repeat('word ', $words)));
    }

    /**
     * @return list<array{category: string, score: int}>
     */
    protected function scores(array $pairs): array
    {
        return array_map(
            fn (string $category, int $score) => ['category' => $category, 'score' => $score],
            array_keys($pairs),
            array_values($pairs),
        );
    }

    public function test_the_five_levels_are_ordered_strongest_first(): void
    {
        $this->assertSame([
            ConfidenceLevel::Strong,
            ConfidenceLevel::Moderate,
            ConfidenceLevel::Emerging,
            ConfidenceLevel::Weak,
            ConfidenceLevel::InsufficientEvidence,
        ], ConfidenceLevel::descending());
    }

    public function test_only_strong_and_moderate_permit_naming_a_direction(): void
    {
        $this->assertTrue(ConfidenceLevel::Strong->permitsConclusion());
        $this->assertTrue(ConfidenceLevel::Moderate->permitsConclusion());

        foreach ([ConfidenceLevel::Emerging, ConfidenceLevel::Weak, ConfidenceLevel::InsufficientEvidence] as $level) {
            $this->assertFalse($level->permitsConclusion(), "{$level->value} must not permit a conclusion.");
            $this->assertTrue($level->isLowConfidence());
        }
    }

    public function test_capping_always_takes_the_weaker_level(): void
    {
        $this->assertSame(
            ConfidenceLevel::Weak,
            ConfidenceLevel::Strong->cappedAt(ConfidenceLevel::Weak),
        );

        $this->assertSame(
            ConfidenceLevel::Weak,
            ConfidenceLevel::Weak->cappedAt(ConfidenceLevel::Strong),
        );
    }

    public function test_score_alone_maps_to_a_level(): void
    {
        $this->assertSame(ConfidenceLevel::Strong, ConfidenceCalculator::fromScore(80));
        $this->assertSame(ConfidenceLevel::Moderate, ConfidenceCalculator::fromScore(65));
        $this->assertSame(ConfidenceLevel::Emerging, ConfidenceCalculator::fromScore(50));
        $this->assertSame(ConfidenceLevel::Weak, ConfidenceCalculator::fromScore(30));
        $this->assertSame(ConfidenceLevel::InsufficientEvidence, ConfidenceCalculator::fromScore(29));
    }

    public function test_no_answers_is_insufficient_evidence(): void
    {
        $this->assertSame(ConfidenceLevel::InsufficientEvidence, ConfidenceCalculator::evidenceCeiling([]));
        $this->assertSame(ConfidenceLevel::InsufficientEvidence, ConfidenceCalculator::evidenceCeiling(['', '   ']));
    }

    public function test_too_few_substantive_answers_is_insufficient_evidence(): void
    {
        $this->assertSame(
            ConfidenceLevel::InsufficientEvidence,
            ConfidenceCalculator::evidenceCeiling(['I dunno', 'maybe', 'sure', 'yeah ok']),
        );
    }

    public function test_brief_answers_cap_the_ceiling_below_a_conclusion(): void
    {
        $ceiling = ConfidenceCalculator::evidenceCeiling($this->answers(10, 9));

        $this->assertSame(ConfidenceLevel::Emerging, $ceiling);
        $this->assertFalse($ceiling->permitsConclusion());
    }

    public function test_substantial_answers_lift_the_ceiling(): void
    {
        $this->assertSame(ConfidenceLevel::Strong, ConfidenceCalculator::evidenceCeiling($this->answers(10, 45)));
        $this->assertSame(ConfidenceLevel::Moderate, ConfidenceCalculator::evidenceCeiling($this->answers(6, 25)));
    }

    /**
     * The whole point of the ceiling: a confident model on a thin assessment
     * must not produce a confident result.
     */
    public function test_a_high_score_on_thin_evidence_is_not_a_strong_signal(): void
    {
        $annotated = ConfidenceCalculator::annotate(
            $this->scores(['Healing & Care' => 95, 'Teaching & Formation' => 40]),
            ['I like helping people', 'I dunno', 'sure', 'my mom is a nurse'],
        );

        $this->assertSame(ConfidenceLevel::InsufficientEvidence->value, $annotated[0]['confidence']);
    }

    public function test_a_leader_inside_the_clustering_threshold_is_capped_at_moderate(): void
    {
        $annotated = ConfidenceCalculator::annotate(
            $this->scores([
                'Healing & Care' => 92,
                'Teaching & Formation' => 92 - (CompetingPathways::CLUSTER_THRESHOLD - 1),
            ]),
            $this->answers(10, 45),
        );

        $this->assertSame(ConfidenceLevel::Moderate->value, $annotated[0]['confidence']);
    }

    public function test_a_clearly_separated_leader_on_good_evidence_is_strong(): void
    {
        $annotated = ConfidenceCalculator::annotate(
            $this->scores(['Healing & Care' => 92, 'Teaching & Formation' => 60]),
            $this->answers(10, 45),
        );

        $this->assertSame(ConfidenceLevel::Strong->value, $annotated[0]['confidence']);
    }

    public function test_annotation_returns_categories_strongest_first_and_keeps_every_one(): void
    {
        $annotated = ConfidenceCalculator::annotate(
            $this->scores(['Low' => 20, 'High' => 90, 'Middle' => 55]),
            $this->answers(10, 45),
        );

        $this->assertSame(['High', 'Middle', 'Low'], array_column($annotated, 'category'));
        $this->assertCount(3, $annotated);
    }

    public function test_overall_confidence_is_that_of_the_leading_category(): void
    {
        $this->assertSame(
            ConfidenceLevel::Strong,
            ConfidenceCalculator::overall(
                $this->scores(['Healing & Care' => 92, 'Teaching & Formation' => 60]),
                $this->answers(10, 45),
            ),
        );
    }

    public function test_overall_confidence_with_no_categories_is_insufficient_evidence(): void
    {
        $this->assertSame(
            ConfidenceLevel::InsufficientEvidence,
            ConfidenceCalculator::overall([], $this->answers(10, 45)),
        );
    }

    /**
     * Blueprint 10.5 forbids "There is not enough information to help you."
     * Every low-confidence explanation must name what is missing.
     */
    public function test_low_confidence_always_names_what_is_missing(): void
    {
        $explained = ConfidenceCalculator::explain(
            $this->scores(['Healing & Care' => 95]),
            ['I dunno', 'sure'],
        );

        $this->assertTrue($explained['level']->isLowConfidence());
        $this->assertNotEmpty($explained['missing_evidence']);
        $this->assertStringNotContainsStringIgnoringCase(
            'not enough information to help',
            $explained['rationale'],
        );
    }

    public function test_it_names_indistinguishable_pathways_as_the_missing_evidence(): void
    {
        $explained = ConfidenceCalculator::explain(
            $this->scores([
                'Healing & Care' => 92,
                'Teaching & Formation' => 92 - (CompetingPathways::CLUSTER_THRESHOLD - 1),
            ]),
            $this->answers(10, 45),
        );

        $this->assertNotEmpty(array_filter(
            $explained['missing_evidence'],
            fn (string $item) => str_contains($item, 'separates your top pathways'),
        ));
    }

    public function test_a_strong_result_states_no_missing_evidence(): void
    {
        $explained = ConfidenceCalculator::explain(
            $this->scores(['Healing & Care' => 92, 'Teaching & Formation' => 60]),
            $this->answers(10, 45),
        );

        $this->assertSame(ConfidenceLevel::Strong, $explained['level']);
        $this->assertSame([], $explained['missing_evidence']);
    }

    public function test_missing_evidence_entries_are_unique(): void
    {
        $explained = ConfidenceCalculator::explain(
            $this->scores(['A' => 20, 'B' => 19]),
            ['short', 'also short'],
        );

        $this->assertSame(
            array_values(array_unique($explained['missing_evidence'])),
            $explained['missing_evidence'],
        );
    }

    /**
     * A stored level that the writer never sees changes nothing. These assert
     * the constraint actually reaches the prompt.
     */
    public function test_the_narrative_prompt_omits_the_confidence_brief_when_there_is_no_level(): void
    {
        $prompt = (new NarrativeSynthesis(['primary_domain' => 'care']))->buildPrompt();

        $this->assertStringNotContainsString('## Confidence', $prompt);
    }

    public function test_a_high_confidence_narrative_may_name_a_direction(): void
    {
        $prompt = (new NarrativeSynthesis(
            ['primary_domain' => 'care'],
            '',
            'en',
            ConfidenceLevel::Strong,
            [],
        ))->buildPrompt();

        $this->assertStringContainsString('Strong signal', $prompt);
        $this->assertStringContainsString('You may name a vocational direction.', $prompt);
        $this->assertStringContainsString('Nothing essential is missing.', $prompt);
    }

    /**
     * Blueprint 10.5: below Moderate the writer must not name a direction and
     * must not fall back on a generic career list.
     */
    public function test_a_low_confidence_narrative_is_forbidden_from_naming_a_direction(): void
    {
        $prompt = (new NarrativeSynthesis(
            ['primary_domain' => 'care'],
            '',
            'en',
            ConfidenceLevel::Weak,
            ['More detail in your answers.'],
        ))->buildPrompt();

        $collapsed = preg_replace('/\s+/', ' ', $prompt);

        $this->assertStringContainsString('You may NOT name a single vocational direction.', $collapsed);
        $this->assertStringContainsString('do not substitute a generic list of careers', $collapsed);
        $this->assertStringContainsString('More detail in your answers.', $collapsed);
        $this->assertStringContainsString('always enough to know what to test next', $collapsed);
    }

    public function test_every_low_confidence_level_forbids_naming_a_direction(): void
    {
        foreach ([ConfidenceLevel::Emerging, ConfidenceLevel::Weak, ConfidenceLevel::InsufficientEvidence] as $level) {
            $prompt = (new NarrativeSynthesis(['x' => 'y'], '', 'en', $level, []))->buildPrompt();

            $this->assertStringContainsString(
                'You may NOT name a single vocational direction.',
                preg_replace('/\s+/', ' ', $prompt),
                "{$level->value} must forbid naming a direction.",
            );
        }
    }

    /**
     * The blueprint bans percentages, dials and match scores in the output
     * layer. A level is words, never a number.
     */
    public function test_a_level_renders_as_the_blueprints_own_wording(): void
    {
        $this->assertSame('Strong signal', ConfidenceLevel::Strong->label());
        $this->assertSame('Insufficient evidence', ConfidenceLevel::InsufficientEvidence->label());

        foreach (ConfidenceLevel::cases() as $level) {
            $this->assertDoesNotMatchRegularExpression('/\d/', $level->label());
        }
    }
}
