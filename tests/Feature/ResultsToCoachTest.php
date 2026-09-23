<?php

namespace Tests\Feature;

use App\Enums\ConfidenceLevel;
use App\Models\Assessment;
use App\Models\FeatureFlag;
use App\Models\ParentConsent;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Not a tool that hands the student a portrait and walks away."
 *
 * The results page used to end at a list, an email box and a link home. It now
 * ends at the coach — through whichever door is actually open to the person
 * reading it, decided by the same predicate the coach enforces.
 */
class ResultsToCoachTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The coach ships dark; every case here is about what happens once an
     * operator has switched it on.
     */
    protected function setUp(): void
    {
        parent::setUp();

        FeatureFlag::updateOrCreate(['key' => 'pathway_coach'], ['name' => 'Pathway Coach', 'is_enabled' => true]);
        Cache::forget('feature_flag:pathway_coach');
    }

    protected function assessmentFor(?User $user): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $user?->id,
            'guest_token' => $user ? null : Str::random(40),
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $assessment->vocationalProfile()->create([
            'opening_synthesis' => 'You come alive when someone needs you to stay.',
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'mode_of_work' => 'hands-on',
            'confidence_level' => ConfidenceLevel::Moderate,
            'missing_evidence' => ['More detail about what you have actually done.'],
        ]);

        return $assessment;
    }

    protected function consentedJunior(): User
    {
        $student = User::factory()->paying()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);

        ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ])->grant();

        return $student->fresh();
    }

    #[Test]
    public function an_entitled_student_is_led_straight_into_the_coach(): void
    {
        $student = $this->consentedJunior();
        $assessment = $this->assessmentFor($student);

        $this->actingAs($student)->get("/assessment/{$assessment->id}/results")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Assessment/Results')
                ->where('coach.state', 'open')
                ->where('coach.href', '/coach')
                ->where('coach.starters.0', 'What would testing Healing & Care look like this month?'));

        $this->actingAs($student)->get('/coach')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('opening', 'first'));
    }

    #[Test]
    public function a_guest_is_asked_to_save_the_portrait_first_and_keeps_it(): void
    {
        $assessment = $this->assessmentFor(null);

        $this->withHeader('X-Guest-Token', $assessment->guest_token)
            ->get("/assessment/{$assessment->id}/results?guest_token={$assessment->guest_token}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coach.state', 'account')
                ->where('coach.href', '/register?guest_token='.$assessment->guest_token));
    }

    #[Test]
    public function a_junior_without_consent_is_sent_to_ask_a_parent(): void
    {
        $student = User::factory()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);
        $assessment = $this->assessmentFor($student);

        $this->actingAs($student)->get("/assessment/{$assessment->id}/results")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coach.state', 'consent')
                ->where('coach.href', '/next'));
    }

    #[Test]
    public function a_consented_junior_nobody_is_paying_for_is_sent_to_checkout(): void
    {
        $student = $this->consentedJunior();
        $student->forceFill(['trial_ends_at' => null])->save();
        $assessment = $this->assessmentFor($student);

        $this->actingAs($student->fresh())->get("/assessment/{$assessment->id}/results")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coach.state', 'checkout')
                ->where('coach.href', '/billing')
                ->where('coach.starters', []));
    }

    #[Test]
    public function a_freshman_is_not_pushed_toward_a_coach_they_cannot_have(): void
    {
        $freshman = User::factory()->create([
            'grade_level' => 9,
            'birthdate' => now()->subYears(14)->toDateString(),
        ]);
        $assessment = $this->assessmentFor($freshman);

        $this->actingAs($freshman)->get("/assessment/{$assessment->id}/results")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coach.state', 'later')
                ->where('coach.href', null));
    }

    #[Test]
    public function no_handoff_is_offered_to_a_coach_that_is_switched_off(): void
    {
        app(FeatureFlagService::class)->toggle('pathway_coach', false);
        $student = $this->consentedJunior();
        $assessment = $this->assessmentFor($student);

        $this->actingAs($student)->get("/assessment/{$assessment->id}/results")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('coach', null));
    }
}
