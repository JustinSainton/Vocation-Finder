<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        FeatureFlag::where('key', 'application_tracking')->update(['is_enabled' => true]);
    }

    public function test_can_create_manual_application(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/applications', [
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'job_url' => 'https://acme.com/jobs/123',
                'source' => 'manual',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'saved');
        $response->assertJsonPath('data.company_name', 'Acme Corp');
    }

    public function test_can_transition_application_status(): void
    {
        $user = User::factory()->create();
        $app = JobApplication::create([
            'user_id' => $user->id,
            'company_name' => 'Test Co',
            'job_title' => 'Tester',
            'status' => 'saved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/applications/{$app->id}", [
                'status' => 'applied',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'applied');

        $this->assertDatabaseHas('application_events', [
            'job_application_id' => $app->id,
            'event_type' => 'status_change',
            'from_status' => 'saved',
            'to_status' => 'applied',
        ]);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $user = User::factory()->create();
        $app = JobApplication::create([
            'user_id' => $user->id,
            'company_name' => 'Test Co',
            'job_title' => 'Tester',
            'status' => 'saved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/applications/{$app->id}", [
                'status' => 'accepted',
            ]);

        $response->assertUnprocessable();
    }

    public function test_analytics_endpoint_returns_funnel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/applications/analytics');

        $response->assertOk();
        $response->assertJsonStructure([
            'funnel',
            'conversion_rates',
            'ghosted_rate',
            'weekly_velocity',
            'total_applications',
        ]);
    }

    public function test_cannot_access_other_users_application(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $app = JobApplication::create([
            'user_id' => $user1->id,
            'company_name' => 'Private Co',
            'job_title' => 'Secret Role',
            'status' => 'saved',
        ]);

        $response = $this->actingAs($user2, 'sanctum')
            ->getJson("/api/v1/applications/{$app->id}");

        $response->assertForbidden();
    }

    public function test_application_tracking_blocked_when_flag_disabled(): void
    {
        FeatureFlag::where('key', 'application_tracking')->update(['is_enabled' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/applications');

        $response->assertNotFound();
    }
}
