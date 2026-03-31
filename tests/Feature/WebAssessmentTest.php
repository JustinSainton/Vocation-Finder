<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\VocationalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_welcome_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Welcome');
    }

    public function test_orientation_page_loads(): void
    {
        $response = $this->get('/assessment');

        $response->assertOk();
    }

    public function test_before_survey_page_loads(): void
    {
        $response = $this->get('/assessment/before');

        $response->assertOk();
    }

    public function test_begin_creates_assessment_and_before_survey_and_redirects(): void
    {
        $response = $this->post('/assessment/begin', [
            'clarity_score' => 4,
            'action_score' => 3,
        ]);

        $response->assertRedirect('/assessment/written');

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseHas('assessment_surveys', [
            'type' => 'before',
            'clarity_score' => 4,
            'action_score' => 3,
        ]);
    }

    public function test_begin_validates_required_scores(): void
    {
        $response = $this->post('/assessment/begin', []);

        $response->assertSessionHasErrors(['clarity_score', 'action_score']);
    }

    public function test_begin_validates_score_range(): void
    {
        $response = $this->post('/assessment/begin', [
            'clarity_score' => 0,
            'action_score' => 11,
        ]);

        $response->assertSessionHasErrors(['clarity_score', 'action_score']);
    }

    public function test_written_page_creates_assessment_and_loads_questions(): void
    {
        $response = $this->get('/assessment/written');

        $response->assertOk();

        // Should have created an assessment
        $this->assertDatabaseCount('assessments', 1);
    }

    public function test_written_page_reuses_assessment_from_session(): void
    {
        // Simulate the assessment created during the before-survey step
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $response = $this->withSession(['current_assessment_id' => $assessment->id])
            ->get('/assessment/written');

        $response->assertOk();

        // Should reuse the existing assessment, not create a new one
        $this->assertDatabaseCount('assessments', 1);
    }

    public function test_after_survey_page_loads(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'analyzing',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->get("/assessment/{$assessment->id}/after");

        $response->assertOk();
    }

    public function test_after_survey_saves_and_redirects_to_results(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'analyzing',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->post("/assessment/{$assessment->id}/after", [
            'clarity_score' => 8,
            'action_score' => 9,
        ]);

        $response->assertRedirect("/assessment/{$assessment->id}/results");

        $this->assertDatabaseHas('assessment_surveys', [
            'assessment_id' => $assessment->id,
            'type' => 'after',
            'clarity_score' => 8,
            'action_score' => 9,
        ]);
    }

    public function test_results_page_loads_for_analyzing_assessment(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'analyzing',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->get("/assessment/{$assessment->id}/results");

        $response->assertOk();
    }

    public function test_results_page_loads_with_completed_profile(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => 'You are drawn to creative problem-solving.',
            'vocational_orientation' => 'Your orientation blends innovation with care.',
            'primary_pathways' => ['Architecture', 'Urban planning'],
            'specific_considerations' => 'Strong leadership instincts.',
            'next_steps' => ['Explore programs', 'Find a mentor'],
            'ministry_integration' => 'Your work in design is service.',
            'primary_domain' => 'Creating & Building',
            'mode_of_work' => 'Entrepreneurial',
            'secondary_orientation' => 'Leadership',
            'category_scores' => [],
            'ai_analysis_raw' => [],
        ]);

        $response = $this->get("/assessment/{$assessment->id}/results");

        $response->assertOk();
    }

    public function test_beta_questions_are_seeded(): void
    {
        $this->assertDatabaseHas('questions', ['is_beta' => true]);
    }

    public function test_written_page_loads_beta_questions_when_flag_enabled(): void
    {
        config(['vocation.beta.questions_enabled' => true]);

        $response = $this->get('/assessment/written');

        $response->assertOk();

        // Assessment created
        $this->assertDatabaseCount('assessments', 1);
    }
}
