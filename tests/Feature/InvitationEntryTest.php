<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\InvitationController;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\CohortInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * How a student invited by their school gets in.
 *
 * The link in every invitation email pointed at an API route no browser
 * request ever reached, so the entry path did not exist at all — these pin the
 * one that replaced it, and the rules it has to apply at the moment the link
 * is used rather than at the moment it was written.
 */
class InvitationEntryTest extends TestCase
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

    protected function invitation(Organization $organization, array $attributes = []): OrganizationInvitation
    {
        return $organization->invitations()->create([
            'email' => 'student@example.com',
            'role' => 'member',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            ...$attributes,
        ]);
    }

    #[Test]
    public function the_link_in_the_invitation_email_reaches_a_page(): void
    {
        $invitation = $this->invitation($this->school());

        $mail = (new OrganizationInvitationNotification($invitation))
            ->toMail(new User)
            ->render();

        /*
         * Asserted as an exact href rather than a substring. The dead route
         * this replaced was `/invitations/{token}/accept`, which *contains*
         * `/invitations/{token}` — a contains-check passes on the broken URL
         * and proves nothing.
         */
        $this->assertStringContainsString('"'.url("/invitations/{$invitation->token}").'"', $mail);
        $this->assertStringNotContainsString("/invitations/{$invitation->token}/accept", $mail);

        $this->get("/invitations/{$invitation->token}")->assertOk();
    }

    /**
     * A login wall here would mean the link only works for the people who did
     * not need it.
     */
    #[Test]
    public function a_student_with_no_account_can_read_it(): void
    {
        $invitation = $this->invitation($this->school());

        $this->get("/invitations/{$invitation->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invitations/Show')
                ->where('organization_name', 'Grace Academy')
                ->where('signed_in_as', null)
            );
    }

    #[Test]
    public function a_made_up_token_finds_nothing(): void
    {
        $this->get('/invitations/'.Str::random(64))->assertNotFound();
        $this->post('/invitations/'.Str::random(64))->assertNotFound();
    }

    #[Test]
    public function accepting_while_signed_in_joins_that_account_and_starts_the_sequence(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school);
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post("/invitations/{$invitation->token}")
            ->assertRedirect('/next');

        $this->assertTrue($school->users()->where('users.id', $student->id)->exists());
        $this->assertSame('member', $school->users()->find($student->id)->pivot->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    /**
     * A signed-out visitor is sent to registration with the token carried in
     * the session, and joins when the account exists.
     */
    #[Test]
    public function registering_from_an_invitation_joins_the_school(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school);

        $this->get("/invitations/{$invitation->token}");
        $this->post("/invitations/{$invitation->token}")->assertRedirect('/register');

        $this->post('/register', [
            'name' => 'Maya',
            'email' => 'maya@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect('/next');

        $student = User::where('email', 'maya@example.com')->firstOrFail();

        $this->assertTrue($school->users()->where('users.id', $student->id)->exists());
        $this->assertNull(session(InvitationController::SESSION_KEY));
    }

    /**
     * Somebody registering on their own is not quietly enrolled in a school
     * because of a token left in the session by a previous page.
     */
    #[Test]
    public function registering_without_an_invitation_joins_nothing(): void
    {
        /*
         * An invitation for somebody else has to be outstanding, or this test
         * passes for the wrong reason: with no rows in the table, code that
         * grabbed "whatever invitation is lying around" would find nothing and
         * look correct.
         */
        $this->invitation($this->school(), ['email' => 'someone-else@example.com']);

        $this->post('/register', [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect('/dashboard');

        $student = User::where('email', 'nobody@example.com')->firstOrFail();

        $this->assertSame(0, $student->organizations()->count());
    }

    #[Test]
    public function an_expired_invitation_joins_nobody(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school, ['expires_at' => now()->subDay()]);
        $student = User::factory()->create();

        $this->actingAs($student)->post("/invitations/{$invitation->token}");

        $this->assertFalse($school->users()->where('users.id', $student->id)->exists());
        $reason = (new CohortInvitation)->blockedReason($invitation);

        $this->assertNotNull($reason, 'An expired invitation was still usable.');
        $this->assertStringContainsString('expired', $reason);
    }

    #[Test]
    public function an_invitation_is_good_once(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post("/invitations/{$invitation->token}");

        $this->expectException(RuntimeException::class);
        (new CohortInvitation)->accept($invitation->fresh(), $second);
    }

    /**
     * The seat is checked when the link is used, not when it was written. An
     * invitation is a copy of a permission, and copies go stale.
     */
    #[Test]
    public function a_school_that_has_run_out_of_seats_refuses_at_the_door(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school);

        for ($seat = 0; $seat < $school->memberLimit(); $seat++) {
            $school->users()->attach(User::factory()->create(), [
                'id' => Str::uuid()->toString(),
                'role' => 'member',
            ]);
        }

        $student = User::factory()->create();

        $this->actingAs($student)
            ->post("/invitations/{$invitation->token}")
            ->assertRedirect("/invitations/{$invitation->token}");

        $this->assertFalse($school->users()->where('users.id', $student->id)->exists());
        $reason = (new CohortInvitation)->blockedReason($invitation);

        $this->assertNotNull($reason, 'The invitation was still usable with every seat taken.');
        $this->assertStringContainsString('no seats left', $reason);
    }

    /**
     * Outstanding invitations count against the roster too. Counting only
     * members lets a school hand out fifty links for twenty seats and find out
     * one disappointed student at a time.
     */
    #[Test]
    public function unaccepted_invitations_take_up_seats(): void
    {
        $school = $this->school();
        $mine = $this->invitation($school);

        for ($seat = 0; $seat < $school->memberLimit(); $seat++) {
            $this->invitation($school, ['email' => "other{$seat}@example.com", 'token' => Str::random(64)]);
        }

        $reason = (new CohortInvitation)->blockedReason($mine);

        $this->assertNotNull($reason, 'Outstanding invitations were not counted against the roster.');
        $this->assertStringContainsString('no seats left', $reason);
    }

    /**
     * Joining a school as a student does not hand the student the school's
     * surfaces. The invitation carries the role it was written with, and
     * nothing upgrades it.
     */
    #[Test]
    public function joining_as_a_student_opens_none_of_the_staff_surfaces(): void
    {
        $school = $this->school();
        $invitation = $this->invitation($school);
        $student = User::factory()->create();

        $this->actingAs($student)->post("/invitations/{$invitation->token}");

        $this->actingAs($student)->get("/org/{$school->slug}/cohort")->assertForbidden();
        $this->actingAs($student)->get("/org/{$school->slug}/settings")->assertForbidden();
    }

    /**
     * On a shared machine the page names the account that is about to join,
     * rather than quietly enrolling whoever happens to be signed in.
     */
    #[Test]
    public function the_page_names_the_account_that_would_join(): void
    {
        $invitation = $this->invitation($this->school());
        $someoneElse = User::factory()->create(['email' => 'not-the-invited@example.com']);

        $this->actingAs($someoneElse)
            ->get("/invitations/{$invitation->token}")
            ->assertInertia(fn ($page) => $page
                ->where('signed_in_as', 'not-the-invited@example.com')
                ->where('invited_email', 'student@example.com')
            );
    }
}
