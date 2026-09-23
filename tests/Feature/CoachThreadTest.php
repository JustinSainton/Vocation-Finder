<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Enums\ConfidenceLevel;
use App\Models\Assessment;
use App\Models\ParentConsent;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Support\ActionQueue;
use App\Support\CoachOpening;
use App\Support\CoachThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The coach as a conversation the student can see, return to, and be spoken
 * to first in.
 *
 * Before this, `POST /coach/message` asked the model and then redirected
 * back to a page with no thread on it — the reply was paid for and thrown
 * away — and the coach waited in silence for a student to think of something
 * to say, which is the decision friction the product exists to remove.
 */
class CoachThreadTest extends TestCase
{
    use RefreshDatabase;

    protected function student(): User
    {
        $student = User::factory()->create([
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

    protected function completedAssessment(User $user, ConfidenceLevel $confidence = ConfidenceLevel::Moderate): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $assessment->vocationalProfile()->create([
            'opening_synthesis' => 'You come alive when someone needs you to stay.',
            'primary_domain' => 'caring for people directly',
            'primary_pathways' => ['Healing & Care'],
            'secondary_orientation' => 'teaching',
            'mode_of_work' => 'hands-on',
            'confidence_level' => $confidence,
            'confidence_rationale' => 'Your answers were specific.',
            'missing_evidence' => ['More detail about what you have actually done.'],
            'next_steps' => ['Ask a nurse what a shift is actually like.'],
        ]);

        return $assessment;
    }

    #[Test]
    public function a_web_turn_is_kept_and_shown_on_the_next_page_load(): void
    {
        PathwayCoachAgent::fake(['Your story suggests you stay when others leave. What year are you in?']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)
            ->post('/coach/message', ['message' => 'I want to work with people who are hurting.'])
            ->assertRedirect();

        $thread = $this->actingAs($student)->get('/coach')->assertOk()
            ->viewData('page')['props']['thread'];

        $this->assertSame(['user', 'assistant'], array_column($thread, 'role'));
        $this->assertSame('I want to work with people who are hurting.', $thread[0]['content']);
        $this->assertSame('Your story suggests you stay when others leave. What year are you in?', $thread[1]['content']);
    }

    #[Test]
    public function the_coach_speaks_first_for_a_student_who_just_finished(): void
    {
        PathwayCoachAgent::fake(['A recurring pattern in your answers is staying. What year are you in?']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)->get('/coach')->assertOk()
            ->assertInertia(fn ($page) => $page->where('opening', CoachOpening::FIRST)->where('thread', []));

        $body = $this->actingAs($student)->post('/coach/open')->assertOk()->streamedContent();

        $events = $this->events($body);
        $this->assertSame('status', $events[0]['type']);
        $this->assertSame(
            'A recurring pattern in your answers is staying. What year are you in?',
            implode('', array_column(array_filter($events, fn ($e) => $e['type'] === 'delta'), 'text')),
        );

        $done = collect($events)->firstWhere('type', 'done');
        $this->assertCount(1, $done['items']);
        $this->assertSame('assistant', $done['items'][0]['role']);

        PathwayCoachAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'GetPathwayProfileTool')
            && str_contains($prompt->prompt, 'you speak first'));
    }

    /**
     * The opening instruction is stored as a `user` row by the SDK. It must
     * never appear as something the student said, never reach their brain,
     * and never count as an exchange — or the coach's stopping rule starts
     * one exchange early.
     */
    #[Test]
    public function the_opening_instruction_is_not_the_students_words(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)->post('/coach/open')->streamedContent();

        $this->assertSame(1, DB::table('agent_conversation_messages')->where('role', 'user')->count());
        $this->assertSame(['assistant'], array_column((new CoachThread)->items($student), 'role'));
        $this->assertSame(0, $student->brainEntries()->count());

        $exchange = (new \ReflectionMethod(PathwayCoachAgent::class, 'exchangeNumber'));
        $agent = (new PathwayCoachAgent($student))->continueLastConversation($student);
        $this->assertSame(1, $exchange->invoke($agent));
    }

    #[Test]
    public function the_coach_does_not_open_twice(): void
    {
        PathwayCoachAgent::fake(['What year are you in?', 'A second opener nobody asked for.']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)->post('/coach/open')->streamedContent();

        $this->actingAs($student)->post('/coach/open')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonCount(1, 'items');

        $this->assertNull((new CoachOpening)->due($student));
    }

    #[Test]
    public function an_opener_still_happens_when_the_model_is_unreachable(): void
    {
        PathwayCoachAgent::fake(fn () => throw new RuntimeException('provider down'));
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Strong);

        $events = $this->events($this->actingAs($student)->post('/coach/open')->streamedContent());

        $spoken = implode('', array_column(array_filter($events, fn ($e) => $e['type'] === 'delta'), 'text'));
        $this->assertStringContainsString('caring for people directly', $spoken);
        $this->assertStringContainsString('more detail about what you have actually done', $spoken);
        $this->assertNotNull(collect($events)->firstWhere('type', 'done'));

        $thread = (new CoachThread)->items($student);
        $this->assertSame(['assistant'], array_column($thread, 'role'));
    }

    /**
     * Low confidence may not name a direction, and the fallback is held to
     * the same rule as the model.
     */
    #[Test]
    public function the_fallback_opener_obeys_the_confidence_level(): void
    {
        $student = $this->student();
        $this->completedAssessment($student, ConfidenceLevel::Weak);

        $text = (new CoachOpening)->fallback($student);

        $this->assertStringNotContainsString('caring for people directly', $text);
        $this->assertStringContainsString('not enough evidence yet', $text);
    }

    #[Test]
    public function a_streamed_turn_arrives_in_pieces_and_is_persisted(): void
    {
        PathwayCoachAgent::fake(['There is meaningful evidence that you stay.']);
        $student = $this->student();
        $this->completedAssessment($student);

        $response = $this->actingAs($student)->post('/coach/stream', ['message' => 'I stayed with my grandmother every night.']);
        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $events = $this->events($response->streamedContent());

        $this->assertGreaterThan(1, count(array_filter($events, fn ($e) => $e['type'] === 'delta')));
        $done = collect($events)->firstWhere('type', 'done');
        $this->assertSame(['user', 'assistant'], array_column($done['items'], 'role'));
        $this->assertSame(1, $student->brainEntries()->count());
    }

    #[Test]
    public function steps_appear_in_the_thread_where_they_were_assigned(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)->post('/coach/message', ['message' => 'I am a junior.']);
        $this->travel(1)->seconds();
        (new ActionQueue)->assign($student, 'Ask the school nurse what a shift is actually like');

        $items = (new CoachThread)->items($student);

        $this->assertSame(['message', 'message', 'step'], array_column($items, 'type'));
        $this->assertSame('Ask the school nurse what a shift is actually like', $items[2]['title']);
    }

    #[Test]
    public function the_coach_opens_again_after_a_real_gap_but_not_on_top_of_an_unanswered_opener(): void
    {
        PathwayCoachAgent::fake(['What year are you in?', 'How did the step go?', 'Nothing.']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student)->post('/coach/open')->streamedContent();
        $this->travel(13)->hours();
        $this->assertNull((new CoachOpening)->due($student), 'An unanswered opener must not be stacked.');

        $this->actingAs($student)->post('/coach/message', ['message' => 'I am a junior.']);
        $this->travel(13)->hours();
        $this->assertSame(CoachOpening::RETURNING, (new CoachOpening)->due($student));
    }

    #[Test]
    public function the_api_thread_hides_internal_prompts_and_keeps_its_old_shape(): void
    {
        PathwayCoachAgent::fake(['What year are you in?']);
        $student = $this->student();
        $this->completedAssessment($student);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/open')
            ->assertOk()
            ->assertJsonPath('message', 'What year are you in?')
            ->assertJsonCount(1, 'items');

        $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/history')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.role', 'assistant')
            ->assertJsonPath('messages.0.content', 'What year are you in?');

        $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/state')
            ->assertOk()
            ->assertJsonPath('opening', null)
            ->assertJsonPath('starters.0', 'What would testing Healing & Care look like this month?');
    }

    #[Test]
    public function a_student_the_coach_refuses_cannot_make_it_open(): void
    {
        $freshman = User::factory()->create([
            'grade_level' => 9,
            'birthdate' => now()->subYears(14)->toDateString(),
        ]);

        $this->actingAs($freshman)->post('/coach/open')->assertForbidden();
        $this->actingAs($freshman, 'sanctum')->postJson('/api/v1/coach/open')->assertForbidden();
    }

    #[Test]
    public function the_kill_switch_still_closes_every_coach_route(): void
    {
        app(FeatureFlagService::class)->toggle('pathway_coach', false);
        $student = $this->student();

        $this->actingAs($student)->post('/coach/open')->assertNotFound();
        $this->actingAs($student)->post('/coach/stream', ['message' => 'Hi'])->assertNotFound();
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/coach/open')->assertNotFound();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function events(string $body): array
    {
        return collect(explode("\n\n", trim($body)))
            ->filter(fn (string $chunk) => str_starts_with($chunk, 'data: '))
            ->map(fn (string $chunk) => json_decode(substr($chunk, 6), true))
            ->values()
            ->all();
    }
}
