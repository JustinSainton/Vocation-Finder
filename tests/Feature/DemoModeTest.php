<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ConversationSession;
use App\Models\Question;
use App\Models\User;
use App\Support\CrisisCheck;
use App\Support\DemoMode;
use App\Support\DemoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    protected function demoUser(): User
    {
        $user = User::factory()->create();
        $user->role = DemoMode::ROLE;
        $user->save();

        return $user;
    }

    public function test_demo_mode_is_off_by_default_even_for_a_demo_account(): void
    {
        $this->assertFalse(config('vocation.demo.enabled'));

        $this->actingAs($this->demoUser())
            ->get('/assessment/written')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('demo', null)
                ->where('questions.0.demo_answer', null));
    }

    public function test_a_student_never_sees_demo_answers_when_demo_mode_is_on(): void
    {
        config(['vocation.demo.enabled' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/assessment/written')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('demo', null)
                ->where('questions.0.demo_answer', null));
    }

    public function test_a_guest_never_sees_demo_answers_when_demo_mode_is_on(): void
    {
        config(['vocation.demo.enabled' => true]);

        $this->get('/assessment/written')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('demo', null)
                ->where('questions.0.demo_answer', null));

        $this->getJson('/api/v1/questions')
            ->assertOk()
            ->assertJsonMissingPath('data.0.demo_answer');
    }

    public function test_a_demo_account_gets_every_written_question_pre_filled(): void
    {
        config(['vocation.demo.enabled' => true]);

        $this->actingAs($this->demoUser())
            ->get('/assessment/written')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('demo.persona', DemoPersona::NAME)
                ->where('demo.clarity.before', 'vague_sense')
                ->where('demo.clarity.after', 'few_options')
                ->has('questions', 20)
                ->where('questions.0.demo_answer', DemoPersona::STANDARD[1])
                ->where('questions.19.demo_answer', DemoPersona::STANDARD[20]));
    }

    public function test_the_beta_set_is_pre_filled_too(): void
    {
        config(['vocation.demo.enabled' => true, 'vocation.beta.questions_enabled' => true]);

        $this->actingAs($this->demoUser())
            ->get('/assessment/written')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('questions', 5)
                ->where('questions.0.demo_answer', DemoPersona::BETA[1])
                ->where('questions.4.demo_answer', DemoPersona::BETA[5]));
    }

    public function test_the_mobile_questions_endpoint_pre_fills_only_for_a_demo_token(): void
    {
        config(['vocation.demo.enabled' => true]);

        $demoToken = $this->demoUser()->createToken('mobile')->plainTextToken;
        $studentToken = User::factory()->create()->createToken('mobile')->plainTextToken;

        $this->withToken($demoToken)
            ->getJson('/api/v1/questions')
            ->assertOk()
            ->assertJsonPath('data.0.demo_answer', DemoPersona::STANDARD[1]);

        $this->app['auth']->forgetGuards();

        $this->withToken($studentToken)
            ->getJson('/api/v1/questions')
            ->assertOk()
            ->assertJsonMissingPath('data.0.demo_answer');
    }

    public function test_the_mobile_account_payload_says_whether_demo_mode_is_on(): void
    {
        $user = $this->demoUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertJsonPath('user.demo', null);

        config(['vocation.demo.enabled' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertJsonPath('user.role', DemoMode::ROLE)
            ->assertJsonPath('user.demo.persona', DemoPersona::NAME)
            ->assertJsonPath('user.demo.clarity.before', 'vague_sense');
    }

    public function test_every_seeded_question_has_a_persona_answer_that_is_not_a_crisis(): void
    {
        $questions = Question::all();
        $this->assertCount(25, $questions);

        foreach ($questions as $question) {
            $answer = DemoPersona::answerFor($question);

            $this->assertNotNull($answer, "No demo answer for question {$question->sort_order} (beta: {$question->is_beta}).");
            $this->assertFalse(
                (new CrisisCheck)->standing($answer)->isEscalation(),
                "Demo answer {$question->sort_order} would trigger the support block.",
            );
        }
    }

    public function test_demo_answers_save_and_complete_into_the_normal_analysis(): void
    {
        Queue::fake();
        config(['vocation.demo.enabled' => true]);
        $user = $this->demoUser();
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->post("/assessment/{$assessment->id}/clarity", [
            'moment' => 'before',
            'standing' => DemoMode::payloadFor($user)['clarity']['before'],
        ])->assertRedirect();

        foreach (Question::where('is_beta', false)->orderBy('sort_order')->get() as $question) {
            $this->actingAs($user)->postJson("/api/v1/assessments/{$assessment->id}/answers", [
                'question_id' => $question->id,
                'response_text' => DemoMode::answerFor($user, $question),
            ])->assertOk()->assertJsonMissingPath('support');
        }

        $this->actingAs($user)
            ->postJson("/api/v1/assessments/{$assessment->id}/complete")
            ->assertOk()
            ->assertJson(['status' => 'analyzing']);

        $this->assertSame(20, $assessment->answers()->count());
        Queue::assertPushed(AnalyzeAssessmentJob::class, fn ($job) => $job->assessment->is($assessment));
    }

    public function test_a_demo_answer_sent_as_a_conversation_transcript_is_stored(): void
    {
        $assessment = Assessment::create([
            'mode' => 'conversation',
            'status' => 'in_progress',
            'guest_token' => str_repeat('a', 64),
            'started_at' => now(),
        ]);
        $session = ConversationSession::create([
            'assessment_id' => $assessment->id,
            'status' => 'active',
            'locale' => 'en',
            'speech_locale' => 'en',
            'current_question_index' => 0,
        ]);

        Prism::fake([
            StructuredResponseFake::make()->withStructured([
                'is_sufficient' => true,
                'follow_up_question' => null,
                'synthesized_answer' => null,
                'reasoning' => 'Specific and personal.',
            ]),
        ]);

        $this->postJson("/api/v1/conversations/{$session->id}/turn", [
            'transcript' => DemoPersona::STANDARD[1],
            'client_processing' => ['stt_engine' => 'demo-typed'],
        ])->assertOk()->assertJsonPath('current_question_index', 1);

        $this->assertSame(
            DemoPersona::STANDARD[1],
            Answer::where('assessment_id', $assessment->id)->value('response_text'),
        );
    }

    public function test_continue_as_demo_does_not_exist_while_demo_mode_is_off(): void
    {
        $this->demoUser();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('demo_login_available', false));
        $this->post('/demo-login')->assertNotFound();
        $this->getJson('/api/v1/auth/demo')->assertJson(['available' => false]);
        $this->postJson('/api/v1/auth/demo')->assertNotFound();
        $this->assertGuest();
    }

    public function test_continue_as_demo_needs_a_demo_account(): void
    {
        config(['vocation.demo.enabled' => true]);
        User::factory()->create();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('demo_login_available', false));
        $this->post('/demo-login')->assertNotFound();
        $this->postJson('/api/v1/auth/demo')->assertNotFound();
    }

    public function test_continue_as_demo_signs_in_on_the_web_and_opens_the_assessment(): void
    {
        config(['vocation.demo.enabled' => true]);
        $demo = $this->demoUser();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('demo_login_available', true));

        $this->post('/demo-login')->assertRedirect('/assessment/written');
        $this->assertAuthenticatedAs($demo);
    }

    public function test_continue_as_demo_hands_the_app_a_demo_token(): void
    {
        config(['vocation.demo.enabled' => true]);
        $demo = $this->demoUser();

        $this->getJson('/api/v1/auth/demo')->assertJson(['available' => true]);

        $token = $this->postJson('/api/v1/auth/demo')
            ->assertOk()
            ->assertJsonPath('user.id', $demo->id)
            ->assertJsonPath('user.demo.persona', DemoPersona::NAME)
            ->json('token');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/questions')
            ->assertJsonPath('data.0.demo_answer', DemoPersona::STANDARD[1]);
    }

    public function test_continue_as_demo_uses_the_most_recently_set_up_demo_account(): void
    {
        config(['vocation.demo.enabled' => true]);
        $this->demoUser()->forceFill(['updated_at' => now()->subDay()])->save();
        $latest = $this->demoUser();

        $this->assertTrue(DemoMode::account()->is($latest));
    }

    public function test_an_admin_can_give_an_account_the_demo_role(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => DemoMode::ROLE,
        ])->assertRedirect();

        $this->assertSame(DemoMode::ROLE, $user->fresh()->role);
    }

    public function test_the_demo_user_command_creates_an_adult_demo_account_on_a_trial(): void
    {
        $this->artisan('demo:user', ['email' => 'demo@example.com', '--password' => 'secret-password'])
            ->expectsOutputToContain('Demo account ready: demo@example.com')
            ->expectsOutputToContain('VOCATION_DEMO_MODE is off')
            ->assertSuccessful();

        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $this->assertSame(DemoMode::ROLE, $user->role);
        $this->assertGreaterThanOrEqual(18, $user->birthdate->age);
        $this->assertTrue($user->onGenericTrial());

        config(['vocation.demo.enabled' => true]);
        $this->assertTrue(DemoMode::isActiveFor($user));
    }
}
