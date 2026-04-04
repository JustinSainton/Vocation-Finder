<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        FeatureFlag::where('key', 'career_profile')->update(['is_enabled' => true]);
    }

    public function test_career_profile_returns_null_when_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/career-profile');

        $response->assertOk();
        $response->assertJson(['data' => null]);
    }

    public function test_can_create_career_profile_via_update(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/career-profile', [
                'work_history' => [
                    ['company' => 'Acme', 'position' => 'Engineer', 'startDate' => '2020-01', 'endDate' => '', 'summary' => 'Built things'],
                ],
                'skills' => [
                    ['name' => 'PHP', 'level' => 'advanced'],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.import_source', 'manual');

        $this->assertDatabaseHas('career_profiles', [
            'user_id' => $user->id,
            'import_source' => 'manual',
        ]);
    }

    public function test_can_delete_career_profile(): void
    {
        $user = User::factory()->create();
        $user->careerProfile()->create([
            'import_source' => 'manual',
            'skills' => [['name' => 'Test']],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/career-profile');

        $response->assertNoContent();
        $this->assertSoftDeleted('career_profiles', ['user_id' => $user->id]);
    }

    public function test_career_profile_blocked_when_flag_disabled(): void
    {
        FeatureFlag::where('key', 'career_profile')->update(['is_enabled' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/career-profile');

        $response->assertNotFound();
    }
}
