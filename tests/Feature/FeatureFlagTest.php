<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_features_endpoint_returns_all_flags(): void
    {
        $response = $this->getJson('/api/v1/features');

        $response->assertOk();
        $response->assertJsonStructure([
            'job_discovery',
            'career_profile',
            'resume_builder',
        ]);
    }

    public function test_all_flags_default_to_disabled(): void
    {
        $response = $this->getJson('/api/v1/features');

        $response->assertOk();
        $data = $response->json();
        foreach ($data as $key => $enabled) {
            $this->assertFalse($enabled, "Flag {$key} should default to false");
        }
    }

    public function test_feature_middleware_returns_404_when_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertNotFound();
    }

    public function test_feature_middleware_allows_access_when_enabled(): void
    {
        FeatureFlag::where('key', 'job_discovery')->update(['is_enabled' => true]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/jobs');

        $response->assertOk();
    }

    public function test_admin_can_toggle_feature_flag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $flag = FeatureFlag::where('key', 'job_discovery')->first();

        $response = $this->actingAs($admin)
            ->put("/admin/feature-flags/{$flag->id}", [
                'is_enabled' => true,
            ]);

        $response->assertRedirect('/admin/feature-flags');

        $this->assertTrue(FeatureFlag::where('key', 'job_discovery')->value('is_enabled'));
    }

    public function test_auth_me_includes_feature_flags(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'user',
            'features',
        ]);
    }
}
