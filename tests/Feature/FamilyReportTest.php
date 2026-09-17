<?php

namespace Tests\Feature;

use App\Enums\GapStatus;
use App\Enums\GapType;
use App\Mail\FamilyUpdateMail;
use App\Models\Action;
use App\Models\BrainEntry;
use App\Models\Gap;
use App\Models\Milestone;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\ParentVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The parent surface, and the line it must not cross.
 *
 * The load-bearing test here is not that the page renders. It is that nothing
 * the student said privately reaches it — asserted against the serialised
 * payload and the rendered email body, because both are what actually leaves
 * the building. A page that looks right while shipping an extra key is the
 * exact failure this invariant exists to prevent.
 */
class FamilyReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A 16-year-old with consent on file, and a private life behind it.
     *
     * Every private thing the student has is planted here on purpose, with
     * distinctive strings, so the leak tests can look for them by name rather
     * than trusting that the payload "looks clean".
     */
    protected function consentedStudent(): array
    {
        $student = User::factory()->create([
            'name' => 'Maya',
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);

        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);
        $consent->grant('203.0.113.7');

        Action::create([
            'user_id' => $student->id,
            'title' => 'Ask your aunt what a shift in the ICU is actually like.',
            'rationale' => 'You keep coming back to hospitals.',
            'reflection' => 'PRIVATEREFLECTION I was terrified to call her.',
            'status' => 'completed',
            'assigned_at' => now()->subWeek(),
            'settled_at' => now(),
        ]);

        $current = Action::create([
            'user_id' => $student->id,
            'title' => 'Sit in on one nursing class at the community college.',
            'rationale' => 'You want to know before you pay for it.',
            'assigned_at' => now(),
        ]);

        Gap::create([
            'user_id' => $student->id,
            'type' => GapType::Information,
            'status' => GapStatus::Open,
            'summary' => 'Has never seen the work up close.',
            'evidence' => 'PRIVATEEVIDENCE I only know it from TV.',
            'source' => 'assessment',
        ]);

        BrainEntry::create([
            'user_id' => $student->id,
            'source' => 'coach',
            'content' => 'PRIVATEBRAIN I think I am doing this for my mom.',
            'context' => 'coach conversation',
            'occurred_at' => now(),
        ]);

        Milestone::factory()->create([
            'user_id' => $student->id,
            'title' => 'Apply to the nursing program.',
            'why' => 'PRIVATEWHY because you are scared you are not good enough.',
            'due_on' => now()->addMonths(3)->toDateString(),
        ]);

        return [$student->fresh(), $consent->fresh(), $current];
    }

    /**
     * Walk a payload and collect every key it contains, at any depth.
     *
     * @param  mixed  $payload
     * @return list<string>
     */
    protected function keysIn($payload, array $found = []): array
    {
        if (! is_array($payload)) {
            return $found;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $found[] = $key;
            }

            $found = $this->keysIn($value, $found);
        }

        return $found;
    }

    public function test_a_parent_with_consent_sees_progress_and_milestones(): void
    {
        [, $consent] = $this->consentedStudent();

        $response = $this->get("/family/{$consent->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Family/Report')
            ->where('summary.student_name', 'Maya')
            ->where('summary.current_action', 'Sit in on one nursing class at the community college.')
            ->where('summary.milestones.0.title', 'Apply to the nursing program.')
        );

        /*
         * The one thing to do is the whole mechanism for involving a family.
         * A report that arrives without it is a status update, which is the
         * thing the vision says does not change anybody's behaviour.
         */
        $summary = $response->viewData('page')['props']['summary'];

        $this->assertNotEmpty($summary['suggested_conversation']);
    }

    /**
     * The invariant, stated against the wire and not the screen.
     */
    public function test_nothing_the_student_said_privately_reaches_the_parents_page(): void
    {
        [, $consent] = $this->consentedStudent();

        $response = $this->get("/family/{$consent->token}");
        $summary = $response->viewData('page')['props']['summary'];

        foreach (ParentVisibility::FORBIDDEN_KEYS as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $this->keysIn($summary),
                "The parent payload carries a `{$forbidden}` key."
            );
        }

        $encoded = json_encode($summary);

        foreach (['PRIVATEREFLECTION', 'PRIVATEEVIDENCE', 'PRIVATEBRAIN', 'PRIVATEWHY'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
    }

    /**
     * The same sentence, sent rather than shown. An email is the easier place
     * to leak, because nobody is looking at it when it goes out.
     */
    public function test_nothing_the_student_said_privately_reaches_the_email(): void
    {
        [, $consent] = $this->consentedStudent();

        $body = (new FamilyUpdateMail($consent))->render();

        $this->assertStringContainsString('Maya', $body);
        $this->assertStringContainsString('Sit in on one nursing class', $body);

        foreach (['PRIVATEREFLECTION', 'PRIVATEEVIDENCE', 'PRIVATEBRAIN', 'PRIVATEWHY'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
    }

    public function test_a_withdrawn_consent_closes_the_page(): void
    {
        [, $consent] = $this->consentedStudent();

        $consent->revoke();

        $this->get("/family/{$consent->token}")->assertForbidden();
    }

    public function test_a_consent_still_waiting_opens_nothing(): void
    {
        $student = User::factory()->create(['grade_level' => 11, 'birthdate' => now()->subYears(16)->toDateString()]);
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $this->get("/family/{$consent->token}")->assertForbidden();
    }

    public function test_a_made_up_token_finds_nothing(): void
    {
        $this->get('/family/'.str_repeat('a', 64))->assertNotFound();
    }

    /**
     * Turning 18 ends the reporting, not because a birthday changes the
     * relationship but because the account becomes the student's own.
     */
    public function test_an_adult_students_report_says_the_account_is_their_own(): void
    {
        $student = User::factory()->create(['birthdate' => now()->subYears(19)->toDateString()]);
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);
        $consent->grant();

        $summary = ParentVisibility::summaryFor($student->fresh());

        $this->assertFalse($summary['reportable']);
        $this->assertStringContainsString('their own', $summary['reason']);
        $this->assertArrayNotHasKey('current_action', $summary);
        $this->assertArrayNotHasKey('milestones', $summary);
    }

    /**
     * No family route may be addressed by a student id. A parent-facing URL
     * that takes a user is one guessed id away from being everybody's report;
     * the token is the permission, so the token must be the address.
     */
    public function test_no_family_route_is_addressed_by_a_student(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'family')) {
                continue;
            }

            $this->assertNotContains('user', $route->parameterNames());
            $this->assertNotContains('student', $route->parameterNames());
            $this->assertContains('token', $route->parameterNames());
        }
    }

    public function test_the_update_goes_only_where_consent_is_currently_granted(): void
    {
        Mail::fake();

        [, $granted] = $this->consentedStudent();

        $withdrawnStudent = User::factory()->create(['grade_level' => 11, 'birthdate' => now()->subYears(16)->toDateString()]);
        $withdrawn = ParentConsent::create([
            'user_id' => $withdrawnStudent->id,
            'parent_name' => 'Another parent',
            'parent_email' => 'withdrawn@example.com',
        ]);
        $withdrawn->grant();
        $withdrawn->revoke();

        $waitingStudent = User::factory()->create(['grade_level' => 11, 'birthdate' => now()->subYears(16)->toDateString()]);
        ParentConsent::create([
            'user_id' => $waitingStudent->id,
            'parent_name' => 'A waiting parent',
            'parent_email' => 'waiting@example.com',
        ]);

        $this->artisan('family:send-updates')->assertSuccessful();

        Mail::assertQueued(FamilyUpdateMail::class, 1);
        Mail::assertQueued(
            FamilyUpdateMail::class,
            fn (FamilyUpdateMail $mail) => $mail->hasTo($granted->parent_email)
        );
    }

    /**
     * A student who has turned 18 since consent was given gets no letter sent
     * about them at all — not even one explaining why it is empty.
     */
    public function test_no_update_is_sent_about_a_student_who_has_turned_eighteen(): void
    {
        Mail::fake();

        $student = User::factory()->create(['birthdate' => now()->subYears(18)->subDay()->toDateString()]);
        $consent = ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);
        $consent->grant();

        $this->artisan('family:send-updates')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
