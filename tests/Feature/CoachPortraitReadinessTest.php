<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Tools\GetPathwayProfileTool;
use App\Enums\ConfidenceLevel;
use App\Models\Assessment;
use App\Models\FeatureFlag;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\CoachOpening;
use App\Support\CoachThread;
use App\Support\PathwayProfileReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachPortraitReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureFlag::updateOrCreate(['key' => 'pathway_coach'], ['name' => 'Pathway Coach', 'is_enabled' => true]);
        Cache::forget('feature_flag:pathway_coach');
    }

    protected function student(): User
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

    protected function analyzingAssessment(User $user): Assessment
    {
        return Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'analyzing',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    protected function portraitAssessment(User $user, string $status = 'completed'): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => $status,
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

    #[Test]
    public function the_coach_does_not_offer_a_first_opener_while_analysis_runs(): void
    {
        $student = $this->student();
        $this->analyzingAssessment($student);

        $this->assertNull((new CoachOpening)->due($student));
        $this->assertSame(PathwayProfileReadiness::STATUS_ANALYZING, (new PathwayProfileReadiness)->portraitStatus($student));
    }

    #[Test]
    public function the_api_open_endpoint_waits_instead_of_speaking_without_a_portrait(): void
    {
        $student = $this->student();
        $assessment = $this->analyzingAssessment($student);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/open')
            ->assertOk()
            ->assertJsonPath('awaiting_portrait', true)
            ->assertJsonPath('portrait_status', 'analyzing')
            ->assertJsonPath('assessment_id', (string) $assessment->id)
            ->assertJsonPath('message', (new PathwayProfileReadiness)->awaitingMessage())
            ->assertJsonPath('items', []);

        $this->assertSame([], (new CoachThread)->items($student));
    }

    #[Test]
    public function the_profile_tool_finds_a_portrait_while_the_assessment_is_still_analyzing(): void
    {
        $student = $this->student();
        $this->portraitAssessment($student, status: 'analyzing');

        $payload = json_decode((new GetPathwayProfileTool($student))->handle(new Request([])), true);

        $this->assertTrue($payload['has_profile']);
        $this->assertSame('caring for people directly', $payload['primary_domain']);
    }

    #[Test]
    public function a_premature_opener_is_replaced_when_the_portrait_lands(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();
        $this->analyzingAssessment($student);

        (new CoachOpening)->recordFallback($student, CoachOpening::FIRST, (new CoachOpening)->fallback($student));

        $this->assertStringContainsString(
            'Before I can be useful',
            (new CoachThread)->messages($student)->sole()->content,
        );
        $this->assertNull((new CoachOpening)->due($student));

        $assessment->vocationalProfile()->create([
            'opening_synthesis' => 'You come alive when someone needs you to stay.',
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'mode_of_work' => 'hands-on',
            'confidence_level' => ConfidenceLevel::Moderate,
            'missing_evidence' => ['More detail about what you have actually done.'],
        ]);

        $this->assertSame(CoachOpening::FIRST, (new CoachOpening)->due($student));

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/open')
            ->assertOk()
            ->assertJsonPath('message', 'What year are you in?');
    }

    #[Test]
    public function the_coach_state_includes_portrait_readiness(): void
    {
        $student = $this->student();
        $assessment = $this->analyzingAssessment($student);

        $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/state')
            ->assertOk()
            ->assertJsonPath('opening', null)
            ->assertJsonPath('portrait_status', 'analyzing')
            ->assertJsonPath('assessment_id', (string) $assessment->id);
    }
}
