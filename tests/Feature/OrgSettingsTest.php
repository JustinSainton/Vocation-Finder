<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The one setting an organization owns rather than we do.
 *
 * Staff reading a student's portrait is on by default — a counsellor who
 * cannot see the result cannot do the job the school bought the tool for —
 * and an administrator can close it without waiting for a release. These pin
 * both halves, plus the plumbing that has to work for the switch to be
 * reachable at all.
 */
class OrgSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function school(): Organization
    {
        return Organization::create([
            'name' => 'Grace Academy',
            'slug' => 'grace-academy',
            'type' => 'school',
        ]);
    }

    protected function join(Organization $organization, User $user, string $role): void
    {
        $organization->users()->attach($user, ['id' => Str::uuid()->toString(), 'role' => $role]);
    }

    /**
     * Every link in `OrgLayout` is `/org/{slug}`, and implicit binding was
     * still resolving on the UUID primary key — so the entire organization
     * surface 404'd and nothing tested it. The settings page cannot be
     * reachable until this is true.
     *
     * Bound per-route rather than with `getRouteKeyName()`, because the
     * platform-admin surface addresses the same model by id and a global
     * change silently breaks every link on it. Both are asserted here so the
     * next person to reach for the global fix finds out immediately.
     */
    #[Test]
    public function organisations_are_addressed_by_slug_and_the_admin_surface_still_by_id(): void
    {
        $organization = $this->school();
        $admin = User::factory()->create();
        $this->join($organization, $admin, 'admin');

        $this->actingAs($admin)->get("/org/{$organization->slug}")->assertOk();
        $this->actingAs($admin)->get("/org/{$organization->id}")->assertNotFound();

        $platformAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($platformAdmin)
            ->get("/admin/organizations/{$organization->id}")
            ->assertOk();
    }

    #[Test]
    public function the_flag_is_on_before_anyone_has_set_it(): void
    {
        $organization = $this->school();

        $this->assertSame([], (array) $organization->settings);
        $this->assertTrue($organization->staffMayReadPortraits());
    }

    #[Test]
    public function an_administrator_can_close_it_and_a_counsellor_loses_the_portrait(): void
    {
        $organization = $this->school();
        $admin = User::factory()->create();
        $counsellor = User::factory()->create();
        $student = User::factory()->create();

        $this->join($organization, $admin, 'admin');
        $this->join($organization, $counsellor, 'mentor');
        $this->join($organization, $student, 'member');

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->actingAs($counsellor)->get("/assessment/{$assessment->id}/results")->assertOk();

        $this->actingAs($admin)
            ->put("/org/{$organization->slug}/settings", [
                Organization::SETTING_STAFF_MAY_READ_PORTRAITS => false,
            ])
            ->assertRedirect();

        $this->actingAs($counsellor)->get("/assessment/{$assessment->id}/results")->assertForbidden();
    }

    /**
     * `settings` is a shared bag. Assigning to it rather than merging would
     * silently drop every key the next setting adds, and nothing about that
     * failure is visible at the call site.
     */
    #[Test]
    public function saving_one_setting_does_not_drop_the_others(): void
    {
        $organization = $this->school();
        $organization->update(['settings' => ['voice_context' => 'church']]);

        $admin = User::factory()->create();
        $this->join($organization, $admin, 'admin');

        $this->actingAs($admin)->put("/org/{$organization->slug}/settings", [
            Organization::SETTING_STAFF_MAY_READ_PORTRAITS => false,
        ]);

        $this->assertSame('church', $organization->fresh()->settings['voice_context']);
    }

    /**
     * Reading a portrait and deciding who may read portraits are different
     * powers. A counsellor holds the first and not the second.
     */
    #[Test]
    public function a_counsellor_cannot_change_the_setting(): void
    {
        $organization = $this->school();
        $counsellor = User::factory()->create();
        $this->join($organization, $counsellor, 'mentor');

        $this->actingAs($counsellor)->get("/org/{$organization->slug}/settings")->assertForbidden();

        $this->actingAs($counsellor)
            ->put("/org/{$organization->slug}/settings", [
                Organization::SETTING_STAFF_MAY_READ_PORTRAITS => false,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function the_setting_must_be_a_decision_not_an_omission(): void
    {
        $organization = $this->school();
        $admin = User::factory()->create();
        $this->join($organization, $admin, 'admin');

        $this->actingAs($admin)
            ->put("/org/{$organization->slug}/settings", [])
            ->assertSessionHasErrors(Organization::SETTING_STAFF_MAY_READ_PORTRAITS);

        $this->assertTrue($organization->fresh()->staffMayReadPortraits());
    }
}
