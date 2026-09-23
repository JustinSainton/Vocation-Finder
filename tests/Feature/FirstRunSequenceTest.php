<?php

namespace Tests\Feature;

use App\Enums\FirstRunStep;
use App\Models\Assessment;
use App\Models\FeatureFlag;
use App\Models\ParentConsent;
use App\Models\User;
use App\Models\VocationalProfile;
use App\Notifications\ParentConsentNotification;
use App\Services\FeatureFlagService;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\FirstRunSequence;
use App\Support\RedTeamLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The first run, end to end.
 *
 * The sequence exists to end somewhere specific: "if a student finishes their
 * first session without one concrete thing to do, we haven't proven anything."
 * Every test here is really about whether the student can reach that, and what
 * is allowed to stop them on the way.
 */
class FirstRunSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected function junior(): User
    {
        return User::factory()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);
    }

    protected function consentFor(User $student): ParentConsent
    {
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $consent->grant();

        return $consent->fresh();
    }

    protected function analyzedAssessment(User $student): Assessment
    {
        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'opening_synthesis' => 'You keep going back to the part nobody has figured out yet.',
        ]);

        return $assessment->fresh();
    }

    /**
     * Payment is made to look like consent nowhere in this system.
     */
    protected function paid(User $student): User
    {
        $student->forceFill(['trial_ends_at' => now()->addDays(30)])->save();

        return $student->fresh();
    }

    public function test_a_student_with_nothing_starts_at_the_assessment(): void
    {
        $this->assertSame(FirstRunStep::Assessment, FirstRunSequence::next($this->junior()));
    }

    public function test_an_unfinished_assessment_is_still_the_assessment(): void
    {
        $student = $this->junior();
        Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $this->assertSame(FirstRunStep::Assessment, FirstRunSequence::next($student->fresh()));
    }

    public function test_a_completed_assessment_without_a_profile_waits_on_results(): void
    {
        $student = $this->junior();
        Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $this->assertSame(FirstRunStep::Results, FirstRunSequence::next($student->fresh()));
    }

    /**
     * A freshman is not stalled. The portrait is the whole product for them
     * this year, and treating it as an incomplete funnel would push a
     * fourteen-year-old toward exactly the foreclosure the blueprint warns
     * about.
     */
    public function test_a_freshman_ends_at_the_portrait_and_that_is_not_a_failure(): void
    {
        $freshman = User::factory()->create(['grade_level' => 9]);
        $this->analyzedAssessment($freshman);

        $step = FirstRunSequence::next($freshman->fresh());

        $this->assertSame(FirstRunStep::Portrait, $step);
        $this->assertTrue($step->isTerminal());
    }

    public function test_a_junior_is_stopped_at_consent_before_anything_is_charged(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);

        $this->assertSame(FirstRunStep::ParentConsent, FirstRunSequence::next($student->fresh()));
    }

    public function test_consent_alone_does_not_open_the_coach(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $this->consentFor($student);

        $this->assertSame(FirstRunStep::Checkout, FirstRunSequence::next($student->fresh()));
    }

    /**
     * The other direction of the same rule: money is not permission.
     */
    public function test_payment_alone_does_not_stand_in_for_consent(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $this->paid($student);

        $this->assertSame(FirstRunStep::ParentConsent, FirstRunSequence::next($student->fresh()));
    }

    public function test_a_paid_consented_junior_goes_to_the_refinement_conversation(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $this->consentFor($student);
        $this->paid($student);

        $this->assertSame(FirstRunStep::Refinement, FirstRunSequence::next($student->fresh()));
    }

    public function test_after_talking_the_sequence_wants_a_first_action(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $this->consentFor($student);
        $this->paid($student);

        (new BrainCapture)->captureCoachTurn($student, 'user', 'i dont know what i want but i know i dont want an office');

        $this->assertSame(FirstRunStep::FirstAction, FirstRunSequence::next($student->fresh()));
    }

    /**
     * The exit criterion for the whole MVP.
     */
    public function test_the_sequence_completes_when_the_student_has_one_thing_to_do(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $this->consentFor($student);
        $this->paid($student);

        (new BrainCapture)->captureCoachTurn($student, 'user', 'i dont know what i want but i know i dont want an office');
        (new ActionQueue)->assign($student->fresh(), 'Ask your aunt what the hardest week of her job looks like.');

        $this->assertSame(FirstRunStep::Complete, FirstRunSequence::next($student->fresh()));
    }

    /**
     * Revoking consent must not strand a student mid-sequence in a state the
     * interface cannot describe — the step is computed, so it simply moves
     * back to the gate that is now unmet.
     */
    public function test_revoking_consent_moves_the_sequence_back_to_the_gate(): void
    {
        $student = $this->junior();
        $this->analyzedAssessment($student);
        $consent = $this->consentFor($student);
        $this->paid($student);

        $consent->revoke();

        $this->assertSame(FirstRunStep::ParentConsent, FirstRunSequence::next($student->fresh()));
    }

    public function test_a_guest_who_finished_is_asked_for_an_account(): void
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        $this->assertSame(FirstRunStep::Account, FirstRunSequence::nextForGuest($assessment));
        $this->assertSame(FirstRunStep::Assessment, FirstRunSequence::nextForGuest(null));
    }

    public function test_every_step_states_a_next_move_rather_than_a_status(): void
    {
        foreach (FirstRunStep::cases() as $step) {
            $this->assertNotSame('', $step->prompt());
            $this->assertStringNotContainsString('%', $step->prompt());
            $this->assertStringNotContainsString('step ', strtolower($step->prompt()));
        }
    }

    public function test_a_parent_pays_but_the_subscription_belongs_to_the_student(): void
    {
        $student = $this->junior();
        $this->consentFor($student);

        $this->assertSame('parent@example.com', $student->fresh()->stripeEmail());
    }

    public function test_an_adult_is_billed_at_their_own_address(): void
    {
        $adult = User::factory()->create(['birthdate' => now()->subYears(19)->toDateString()]);

        $this->assertSame($adult->email, $adult->stripeEmail());
    }

    public function test_checkout_is_refused_before_a_parent_has_said_yes(): void
    {
        $student = $this->junior();

        $this->actingAs($student)
            ->post('/billing/checkout/student', ['plan' => 'individual_monthly'])
            ->assertSessionHas('error');
    }

    public function test_the_coach_route_does_not_exist_while_the_flag_is_off(): void
    {
        $student = $this->junior();
        $this->consentFor($student);
        app(FeatureFlagService::class)->toggle('pathway_coach', false);

        $this->actingAs($student->fresh())->get('/coach')->assertNotFound();
    }

    public function test_the_coach_route_turns_an_unentitled_student_back_to_the_sequence(): void
    {
        $this->enableCoach();
        $freshman = User::factory()->create(['grade_level' => 9]);

        $this->actingAs($freshman)
            ->get('/coach')
            ->assertRedirect(route('first-run'));
    }

    public function test_a_consented_junior_reaches_the_coach(): void
    {
        $this->enableCoach();
        $student = $this->junior();
        $this->consentFor($student);

        $this->actingAs($student->fresh())->get('/coach')->assertOk();
    }

    /**
     * Without this the age gate is a dead end: a student nominates a parent
     * and nothing ever reaches them.
     */
    public function test_nominating_a_parent_actually_sends_them_the_link(): void
    {
        Notification::fake();
        $student = $this->junior();

        $this->actingAs($student)
            ->post('/parent-consent', [
                'parent_name' => 'A parent',
                'parent_email' => 'parent@example.com',
            ])
            ->assertRedirect();

        Notification::assertSentOnDemand(
            ParentConsentNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'parent@example.com',
        );
    }

    /**
     * A second nomination of the same parent must not mint a second token —
     * two live links to the same account is a permission surface nobody is
     * tracking.
     */
    public function test_nominating_the_same_parent_twice_reuses_the_one_link(): void
    {
        Notification::fake();
        $student = $this->junior();

        foreach ([1, 2] as $ignored) {
            $this->actingAs($student)->post('/parent-consent', [
                'parent_name' => 'A parent',
                'parent_email' => 'parent@example.com',
            ]);
        }

        $this->assertSame(1, $student->parentConsents()->count());
    }

    /**
     * The consent request is not a preview of the product. It carries the
     * student's name and nothing they wrote.
     */
    public function test_the_consent_email_states_the_privacy_boundary_and_quotes_nothing(): void
    {
        $student = $this->junior();
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);
        (new BrainCapture)->captureDirect(
            tap($student, fn ($s) => $consent->grant())->fresh(),
            'i am scared i will pick wrong and waste four years',
        );

        $mail = (new ParentConsentNotification($consent->fresh()))->toMail($student);
        $body = strtolower(implode(' ', array_merge($mail->introLines, $mail->outroLines)));

        $this->assertStringContainsString('will not see', $body);
        $this->assertStringContainsString('is ever deleted', $body);
        $this->assertStringNotContainsString('waste four years', $body);
    }

    /**
     * Hand-written copy needs the same Layer 8 guard as generated copy. It is
     * exactly where determinism ("the right path for her") creeps in unnoticed,
     * because nobody thinks of a mailable as something that needs checking.
     */
    public function test_the_consent_email_passes_the_red_team_lint(): void
    {
        $student = $this->junior();
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $mail = (new ParentConsentNotification($consent))->toMail($student);
        $copy = implode(' ', array_merge([$mail->subject], $mail->introLines, $mail->outroLines));

        $this->assertSame([], RedTeamLint::blocking(RedTeamLint::inspect($copy)));
        $this->assertSame([], RedTeamLint::warnings(RedTeamLint::inspect($copy)));
    }

    public function test_a_parent_says_yes_without_making_an_account(): void
    {
        $student = $this->junior();
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $this->get("/consent/{$consent->token}")->assertOk();
        $this->post("/consent/{$consent->token}")->assertRedirect();

        $this->assertTrue($consent->fresh()->isGranted());
    }

    public function test_a_parent_can_withdraw_consent_and_nothing_is_deleted(): void
    {
        $student = $this->junior();
        $consent = $this->consentFor($student);
        (new BrainCapture)->captureDirect($student->fresh(), 'i want to work somewhere that isnt an office');

        $this->delete("/consent/{$consent->token}")->assertRedirect();

        $this->assertFalse($consent->fresh()->isGranted());
        $this->assertSame(1, $student->brainEntries()->count());
    }

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }
}
