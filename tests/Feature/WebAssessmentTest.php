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

    public function test_written_page_creates_assessment_and_loads_questions(): void
    {
        $response = $this->get('/assessment/written');

        $response->assertOk();

        // Should have created an assessment
        $this->assertDatabaseCount('assessments', 1);
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
}
