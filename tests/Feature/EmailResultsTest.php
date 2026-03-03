<?php

namespace Tests\Feature;

use App\Jobs\EmailResultsJob;
use App\Mail\ResultsMail;
use App\Models\Assessment;
use App\Models\VocationalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_email_results_dispatches_job(): void
    {
        Queue::fake();

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => 'Test synthesis.',
            'vocational_orientation' => 'Test orientation.',
            'primary_pathways' => ['Pathway 1'],
            'specific_considerations' => 'Test considerations.',
            'next_steps' => ['Step 1'],
            'ministry_integration' => 'Test ministry integration.',
            'primary_domain' => 'Creating & Building',
            'mode_of_work' => 'Collaborative',
            'secondary_orientation' => 'Leadership',
            'category_scores' => [],
            'ai_analysis_raw' => [],
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/results/email",
            ['email' => 'test@example.com'],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertOk();
        $response->assertJson(['message' => 'Results will be emailed shortly.']);

        Queue::assertPushed(EmailResultsJob::class, function ($job) use ($assessment) {
            return $job->assessment->id === $assessment->id
                && $job->email === 'test@example.com';
        });
    }

    public function test_email_results_requires_valid_email(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/results/email",
            ['email' => 'not-an-email'],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertStatus(422);
    }

    public function test_email_results_returns_422_if_no_profile(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'analyzing',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/results/email",
            ['email' => 'test@example.com'],
            ['X-Guest-Token' => $assessment->guest_token]
        );

        $response->assertStatus(422);
    }

    public function test_email_job_sends_results_mail(): void
    {
        Mail::fake();

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $profile = VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => 'Test synthesis.',
            'vocational_orientation' => 'Test orientation.',
            'primary_pathways' => ['Pathway 1'],
            'specific_considerations' => 'Test considerations.',
            'next_steps' => ['Step 1'],
            'ministry_integration' => 'Test ministry integration.',
            'primary_domain' => 'Creating & Building',
            'mode_of_work' => 'Collaborative',
            'secondary_orientation' => 'Leadership',
            'category_scores' => [],
            'ai_analysis_raw' => [],
        ]);

        $job = new EmailResultsJob($assessment, 'test@example.com');
        $job->handle();

        Mail::assertSent(ResultsMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }
}
