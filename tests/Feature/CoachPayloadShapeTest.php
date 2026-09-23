<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Data\Coach\BrainstormInvitationData;
use App\Data\Coach\ReadinessData;
use App\Enums\GapType;
use App\Enums\HabitCadence;
use App\Enums\ReadinessLevel;
use App\Models\Assessment;
use App\Models\FeatureFlag;
use App\Models\Gap;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\ActionQueue;
use App\Support\BrainstormSchedule;
use App\Support\CoachOpening;
use App\Support\CoachStarters;
use App\Support\CrisisCheck;
use App\Support\HabitTracker;
use App\Support\ReadinessCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The coach payloads are Laravel Data objects, and the TypeScript for the web
 * and the Expo app is generated from them. These pin the wire format to what
 * the hand-built arrays sent before, byte for byte, so a shipped mobile build
 * keeps reading what it was written against.
 */
class CoachPayloadShapeTest extends TestCase
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

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $assessment->vocationalProfile()->create([
            'opening_synthesis' => 'You come alive when someone needs you to stay.',
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'next_steps' => ['Ask a nurse what a shift is actually like.'],
        ]);

        return $student->fresh();
    }

    #[Test]
    public function the_api_state_is_byte_identical_to_the_arrays_it_replaced(): void
    {
        $student = $this->student();
        $gap = Gap::create(['user_id' => $student->id, 'type' => GapType::Information, 'summary' => 'Has never seen the work up close.']);
        (new ActionQueue)->assign($student, 'Ask your counselor about shadow day', null, $gap);
        (new HabitTracker)->prescribe($student, $gap, 'Write down one thing you noticed', HabitCadence::Daily, 'Attention compounds.');
        $student->readinessSnapshots()->create(['level' => ReadinessLevel::Exploring, 'factors' => [], 'reason' => 'Finished the assessment', 'captured_at' => now()->subDay()]);
        $student->readinessSnapshots()->create(['level' => ReadinessLevel::Exploring, 'factors' => [], 'reason' => null, 'captured_at' => now()]);

        $legacy = [
            'current_action' => (new ActionQueue)->current($student)?->only(['id', 'title', 'rationale']),
            'readiness' => (new ReadinessCalculator)->explain($student),
            'habits' => (new HabitTracker)->forStudent($student),
            'invitation' => (new BrainstormSchedule)->invitation($student),
            'starters' => (new CoachStarters)->for($student),
            'opening' => (new CoachOpening)->due($student),
        ];

        $this->assertCount(2, $legacy['readiness']['history']);
        $this->assertArrayNotHasKey('because', $legacy['readiness']['history'][1]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/state')->assertOk();

        $this->assertSame(json_encode($legacy), $response->getContent());
    }

    #[Test]
    public function readiness_and_an_invitation_with_a_recurring_theme_round_trip_unchanged(): void
    {
        $readiness = [
            'level' => 'exploring',
            'level_label' => 'Exploring',
            'level_description' => 'Still looking.',
            'what_moves_it' => 'Name one thing you have done.',
            'factors' => ['self_knowledge' => ['label' => 'Self-knowledge', 'standing' => 'Some', 'move' => 'Write it down.']],
            'history' => [
                ['on' => '2026-09-01', 'level' => 'Exploring', 'because' => 'Finished the assessment'],
                ['on' => '2026-09-08', 'level' => 'Exploring'],
            ],
        ];

        $invitation = [
            'opens_with' => [
                'term' => 'grandmother',
                'entry_count' => 3,
                'first_said_on' => '2026-06-01',
                'last_said_on' => '2026-09-01',
                'in_their_words' => [['said_on' => '2026-06-01', 'content' => 'I stayed with my grandmother.']],
                'needs_human' => false,
            ],
            'prompt' => 'You have come back to this 3 times since 2026-06-01.',
        ];

        $this->assertSame(json_encode($readiness), json_encode(ReadinessData::from($readiness)));
        $this->assertSame(json_encode($invitation), json_encode(BrainstormInvitationData::from($invitation)));
    }

    #[Test]
    public function history_items_and_legacy_messages_keep_their_keys(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/message', ['message' => 'I am a junior.'])->assertOk();
        $this->travel(1)->seconds();
        (new ActionQueue)->assign($student, 'Ask the school nurse what a shift is actually like');

        $json = $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/history')->assertOk()->json();

        $this->assertSame(['messages', 'items'], array_keys($json));
        $this->assertSame(['type', 'id', 'role', 'content', 'at'], array_keys($json['items'][0]));
        $this->assertSame(['type', 'id', 'title', 'rationale', 'status', 'at'], array_keys($json['items'][2]));
        $this->assertSame(['id', 'role', 'content', 'at'], array_keys($json['messages'][0]));
        $this->assertCount(2, $json['messages']);
    }

    #[Test]
    public function a_settled_turn_carries_the_message_the_thread_the_step_and_starters(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();
        (new ActionQueue)->assign($student, 'Ask the school nurse what a shift is actually like', 'Seeing it settles it.');

        $json = $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/message', ['message' => 'I am a junior.'])->assertOk()->json();

        $this->assertSame(['message', 'items', 'current_action', 'starters'], array_keys($json));
        $this->assertSame(['id', 'title', 'rationale'], array_keys($json['current_action']));
        $this->assertSame('What year are you in?', $json['message']);
    }

    #[Test]
    public function the_crisis_reply_is_the_fixed_support_message_unchanged(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/coach/message', ['message' => 'I keep thinking I want to die and I do not know who to tell.'])
            ->assertOk();

        $this->assertSame(json_encode(['support' => (new CrisisCheck)->support()]), $response->getContent());
    }

    #[Test]
    public function the_results_handoff_keeps_its_keys(): void
    {
        $student = $this->student();
        $assessment = $student->assessments()->first();

        $coach = $this->actingAs($student)->get("/assessment/{$assessment->id}/results")->assertOk()
            ->viewData('page')['props']['coach'];

        $this->assertSame(['state', 'eyebrow', 'headline', 'body', 'href', 'cta', 'starters'], array_keys($coach));
        $this->assertSame('open', $coach['state']);
    }
}
