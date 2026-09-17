<?php

namespace Tests\Feature;

use App\Ai\Agents\CategoryDisambiguation;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use Database\Seeders\VocationalCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Covers the second interpretive pass: the agent's prompt construction and the
 * job-side logic that applies (or discards) its result.
 */
class CategoryDisambiguationTest extends TestCase
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

    protected function question(string $text): Question
    {
        $category = QuestionCategory::firstOrCreate(
            ['slug' => 'test-category'],
            ['name' => 'Test Category', 'sort_order' => 1],
        );

        return Question::create([
            'category_id' => $category->id,
            'question_text' => $text,
            'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    protected function callJobMethod(AnalyzeAssessmentJob $job, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($job, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($job, $args);
    }

    public function test_prompt_contains_the_competing_categories_and_excludes_the_rest(): void
    {
        $agent = new CategoryDisambiguation(
            competingSlugs: ['healing-care', 'nourishing-hospitality'],
            answers: [['question' => 'Tell me about a hard moment.', 'response' => 'My friend was sick.']],
        );

        $prompt = $agent->buildPrompt();

        $this->assertStringContainsString('Healing & Care', $prompt);
        $this->assertStringContainsString('Nourishing & Hospitality', $prompt);
        $this->assertStringNotContainsString('Finance & Economics', $prompt);
        $this->assertStringContainsString('My friend was sick.', $prompt);
        $this->assertStringContainsString('Ask yourself:', $prompt);
    }

    public function test_prompt_states_whether_the_pathways_are_declared_adjacent(): void
    {
        $adjacent = (new CategoryDisambiguation(
            competingSlugs: ['healing-care', 'nourishing-hospitality'],
            answers: [],
            areAdjacent: true,
        ))->buildPrompt();

        $this->assertStringContainsString('declared adjacent', $adjacent);
        $this->assertStringNotContainsString('not declared adjacent', $adjacent);

        $unrelated = (new CategoryDisambiguation(
            competingSlugs: ['finance-economics', 'protecting-defending'],
            answers: [],
            areAdjacent: false,
        ))->buildPrompt();

        $this->assertStringContainsString('not declared adjacent', $unrelated);
        $this->assertStringContainsString('multi-dimensional', $unrelated);
    }

    public function test_instructions_carry_the_blueprint_engine_rules(): void
    {
        $instructions = $this->collapse(
            (string) (new CategoryDisambiguation(['healing-care'], []))->instructions()
        );

        $this->assertStringContainsString('Object over activity', $instructions);
        $this->assertStringContainsString('Burdens outweigh enjoyment', $instructions);
        $this->assertStringContainsString('Distortion is still evidence', $instructions);
        $this->assertStringContainsString('Multi-dimensional is a valid answer', $instructions);
    }

    public function test_instructions_permit_declining_to_resolve(): void
    {
        // Collapsed because the instructions are hard-wrapped; a phrase may
        // straddle a line break.
        $instructions = $this->collapse(
            (string) (new CategoryDisambiguation(['healing-care'], []))->instructions()
        );

        $this->assertStringContainsString('resolved` to false', $instructions);
        $this->assertStringContainsString('testable next step', $instructions);
    }

    protected function collapse(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    public function test_instructions_forbid_declaring_destiny(): void
    {
        $instructions = $this->collapse(
            (string) (new CategoryDisambiguation(['healing-care'], []))->instructions()
        );

        $this->assertStringContainsString('destiny', $instructions);
        $this->assertStringContainsString('interpreting evidence, not declaring fate', $instructions);
    }

    public function test_answers_for_prompt_falls_back_to_the_audio_transcript(): void
    {
        // Audio-mode assessments store their content in audio_transcript.
        // Reading response_text alone would send the model empty responses.
        $assessment = $this->assessment();
        $assessment->answers()->create([
            'question_id' => $this->question('What did you notice?')->id,
            'response_text' => null,
            'audio_transcript' => 'I could not walk away from her.',
        ]);

        $answers = $this->callJobMethod(new AnalyzeAssessmentJob($assessment), 'answersForPrompt', []);

        $this->assertCount(1, $answers);
        $this->assertSame('What did you notice?', $answers[0]['question']);
        $this->assertSame('I could not walk away from her.', $answers[0]['response']);
    }

    public function test_answers_for_prompt_drops_blank_responses(): void
    {
        $assessment = $this->assessment();
        $assessment->answers()->create([
            'question_id' => $this->question('Answered')->id,
            'response_text' => 'Something real.',
        ]);
        $assessment->answers()->create([
            'question_id' => $this->question('Skipped')->id,
            'response_text' => null,
        ]);

        $answers = $this->callJobMethod(new AnalyzeAssessmentJob($assessment), 'answersForPrompt', []);

        $this->assertCount(1, $answers);
        $this->assertSame('Something real.', $answers[0]['response']);
    }

    public function test_resolved_ranking_overlays_only_the_competing_categories(): void
    {
        $job = new AnalyzeAssessmentJob($this->assessment());

        $analysis = ['category_scores' => [
            ['category' => 'Healing & Care', 'score' => 82, 'rationale' => 'first pass'],
            ['category' => 'Nourishing & Hospitality', 'score' => 80, 'rationale' => 'first pass'],
            ['category' => 'Law & Policy', 'score' => 31, 'rationale' => 'first pass'],
        ]];

        $result = $this->callJobMethod($job, 'applyResolvedRanking', [$analysis, [
            ['category' => 'Healing & Care', 'score' => 91, 'rationale' => 'moved toward the pain'],
            ['category' => 'Nourishing & Hospitality', 'score' => 62, 'rationale' => 'welcome was secondary'],
        ]]);

        $scores = collect($result['category_scores'])->keyBy('category');

        $this->assertSame(91, $scores['Healing & Care']['score']);
        $this->assertSame('moved toward the pain', $scores['Healing & Care']['rationale']);
        $this->assertSame(62, $scores['Nourishing & Hospitality']['score']);

        // Untouched by the second pass.
        $this->assertSame(31, $scores['Law & Policy']['score']);
        $this->assertSame('first pass', $scores['Law & Policy']['rationale']);
    }

    public function test_resolved_ranking_matches_categories_despite_inflection(): void
    {
        $job = new AnalyzeAssessmentJob($this->assessment());

        $analysis = ['category_scores' => [
            ['category' => 'Healing & Care', 'score' => 82, 'rationale' => 'first pass'],
        ]];

        $result = $this->callJobMethod($job, 'applyResolvedRanking', [$analysis, [
            ['category' => 'healing and care', 'score' => 90, 'rationale' => 'resolved'],
        ]]);

        $this->assertSame(90, $result['category_scores'][0]['score']);
    }

    public function test_an_empty_ranking_leaves_the_first_pass_untouched(): void
    {
        $job = new AnalyzeAssessmentJob($this->assessment());

        $analysis = ['category_scores' => [
            ['category' => 'Healing & Care', 'score' => 82, 'rationale' => 'first pass'],
        ]];

        $this->assertSame(
            $analysis,
            $this->callJobMethod($job, 'applyResolvedRanking', [$analysis, []]),
        );
    }

    public function test_a_clear_leader_skips_the_second_pass_entirely(): void
    {
        // No AI provider is configured in tests; if this tried to call out,
        // it would fail rather than return the input unchanged.
        $job = new AnalyzeAssessmentJob($this->assessment());

        $analysis = ['category_scores' => [
            ['category' => 'Healing & Care', 'score' => 91, 'rationale' => 'clear'],
            ['category' => 'Law & Policy', 'score' => 30, 'rationale' => 'weak'],
        ]];

        $this->assertSame(
            $analysis,
            $this->callJobMethod($job, 'disambiguateCompetingPathways', [$analysis, 'test-model']),
        );
    }

    public function test_a_failed_second_pass_preserves_the_first_pass_scores(): void
    {
        // Non-fatal by design: a worse result beats losing the analysis.
        $job = new AnalyzeAssessmentJob($this->assessment());

        $analysis = ['category_scores' => [
            ['category' => 'Healing & Care', 'score' => 82, 'rationale' => 'first pass'],
            ['category' => 'Nourishing & Hospitality', 'score' => 80, 'rationale' => 'first pass'],
        ]];

        $result = $this->callJobMethod($job, 'disambiguateCompetingPathways', [$analysis, 'nonexistent-model']);

        $this->assertSame($analysis['category_scores'], $result['category_scores']);
    }
}
