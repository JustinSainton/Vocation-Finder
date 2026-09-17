<?php

namespace Tests\Feature;

use App\Enums\FeedbackQuestion;
use App\Enums\FeedbackStanding;
use App\Mail\ResultsMail;
use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use App\Models\VocationalProfile;
use App\Services\GuestUpgradeService;
use App\Support\AssessmentAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The results page used to authorize nobody: route-model binding resolved the
 * assessment and rendered it, so a UUID was the only thing standing between a
 * stranger and a minor's verbatim answers and full vocational portrait.
 *
 * The rule now has two ways in and no expiry — the signed-in owner, or the
 * holder of the assessment's own secret. The link must keep working forever,
 * so these tests pin "still open" as hard as they pin "still closed."
 */
class AssessmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function guestAssessment(): Assessment
    {
        return Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);
    }

    protected function ownedAssessment(User $user): Assessment
    {
        return Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);
    }

    #[Test]
    public function a_stranger_holding_only_the_id_is_refused(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results")->assertForbidden();
    }

    #[Test]
    public function a_stranger_guessing_the_token_is_refused(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results?t=".Str::random(64))
            ->assertForbidden();
    }

    /**
     * A prefix of the real token must not pass. `hash_equals` compares whole
     * strings, but a future refactor to str_starts_with would look harmless.
     */
    #[Test]
    public function a_prefix_of_the_token_is_refused(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results?t=".substr($assessment->guest_token, 0, 32))
            ->assertForbidden();
    }

    #[Test]
    public function an_empty_token_is_refused(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results?t=")->assertForbidden();
    }

    /**
     * An assessment with no token cannot be unlocked by sending no token.
     * Guarding against the null-equals-null hole, which is the way this class
     * of check usually fails open.
     */
    #[Test]
    public function an_owned_assessment_cannot_be_opened_by_an_absent_token(): void
    {
        $assessment = $this->ownedAssessment(User::factory()->create());

        $this->get("/assessment/{$assessment->id}/results")->assertForbidden();
        $this->get("/assessment/{$assessment->id}/results?t=")->assertForbidden();
    }

    #[Test]
    public function a_different_student_is_refused(): void
    {
        $assessment = $this->ownedAssessment(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/assessment/{$assessment->id}/results")
            ->assertForbidden();
    }

    #[Test]
    public function the_owner_needs_no_token(): void
    {
        $owner = User::factory()->create();
        $assessment = $this->ownedAssessment($owner);

        $this->actingAs($owner)
            ->get("/assessment/{$assessment->id}/results")
            ->assertOk();
    }

    /**
     * The link a student bookmarks. This is the one that has to keep working.
     */
    #[Test]
    public function the_link_opens_for_the_student_who_holds_it(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results?t={$assessment->guest_token}")
            ->assertOk();
    }

    /**
     * Sessions expire; the student's link must not. Travelling well past any
     * plausible session lifetime and opening it again.
     */
    #[Test]
    public function the_link_still_opens_months_later(): void
    {
        $assessment = $this->guestAssessment();
        $link = "/assessment/{$assessment->id}/results?t={$assessment->guest_token}";

        $this->travel(9)->months();

        $this->get($link)->assertOk();
    }

    /**
     * Being signed into some other account is not a reason to revoke a secret
     * someone holds. The previous API rule required the caller to be a guest
     * as well, which locked students out of their own bookmarks.
     */
    #[Test]
    public function holding_the_token_works_even_when_signed_in_elsewhere(): void
    {
        $assessment = $this->guestAssessment();

        $this->actingAs(User::factory()->create())
            ->get("/assessment/{$assessment->id}/results?t={$assessment->guest_token}")
            ->assertOk();
    }

    /**
     * Once inside, navigation must not have to carry the secret on every link.
     */
    #[Test]
    public function the_token_is_remembered_for_the_rest_of_the_visit(): void
    {
        $assessment = $this->guestAssessment();

        $this->get("/assessment/{$assessment->id}/results?t={$assessment->guest_token}")->assertOk();

        $this->get("/assessment/{$assessment->id}/results")->assertOk();
    }

    /**
     * And remembering it for one assessment must not open another.
     */
    #[Test]
    public function remembering_one_token_does_not_open_a_second_assessment(): void
    {
        $mine = $this->guestAssessment();
        $theirs = $this->guestAssessment();

        $this->get("/assessment/{$mine->id}/results?t={$mine->guest_token}")->assertOk();

        $this->get("/assessment/{$theirs->id}/results")->assertForbidden();
    }

    /**
     * Remembered tokens are keyed per assessment, so a student who retakes the
     * assessment does not lose their way back into the first one. A single
     * shared key would not be a security hole — the stored token is still only
     * ever compared against that assessment's own — but the second visit would
     * silently evict the first, and the older result is exactly the one whose
     * link they no longer have to hand.
     */
    #[Test]
    public function taking_a_second_assessment_does_not_evict_the_first(): void
    {
        $first = $this->guestAssessment();
        $second = $this->guestAssessment();

        $this->get("/assessment/{$first->id}/results?t={$first->guest_token}")->assertOk();
        $this->get("/assessment/{$second->id}/results?t={$second->guest_token}")->assertOk();

        $this->get("/assessment/{$first->id}/results")->assertOk();
        $this->get("/assessment/{$second->id}/results")->assertOk();
    }

    #[Test]
    public function the_header_still_authorizes_the_api(): void
    {
        $assessment = $this->guestAssessment();

        // Not asserting 200 — that endpoint 404s until the profile exists.
        // What matters here is that the header still gets past the gate.
        $this->getJson("/api/v1/assessments/{$assessment->id}/results", [
            'X-Guest-Token' => $assessment->guest_token,
        ])->assertStatus(404);

        $this->getJson("/api/v1/assessments/{$assessment->id}/results")
            ->assertForbidden();
    }

    /**
     * Registration clears the token, so the bookmark stops working — but the
     * student is the owner by then and reaches it while signed in. Pinned
     * because the two halves must not be changed independently.
     */
    #[Test]
    public function upgrading_moves_access_from_the_token_to_the_account(): void
    {
        $assessment = $this->guestAssessment();
        $token = $assessment->guest_token;
        $student = User::factory()->create();

        app(GuestUpgradeService::class)->upgrade($student, $token);

        $this->get("/assessment/{$assessment->id}/results?t={$token}")->assertForbidden();

        $this->actingAs($student)
            ->get("/assessment/{$assessment->id}/results")
            ->assertOk();
    }

    #[Test]
    public function the_rule_is_written_once(): void
    {
        $duplicates = [];

        foreach (['app/Http/Controllers', 'app/Http/Middleware'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                if (str_contains($file->getContents(), 'guest_token')
                    && str_contains($file->getContents(), 'hash_equals')) {
                    $duplicates[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            'A controller is comparing guest tokens itself. That is how the results page came to have no check at all — use AssessmentAccess.',
        );
    }

    #[Test]
    public function the_helper_and_the_route_agree(): void
    {
        $assessment = $this->guestAssessment();

        $request = Request::create(
            "/assessment/{$assessment->id}/results",
            parameters: [AssessmentAccess::TOKEN_PARAM => $assessment->guest_token],
        );

        $this->assertTrue(AssessmentAccess::permits($request, $assessment));
    }

    /**
     * Organisation staff have no path to a member's full portrait.
     *
     * The org member page used to link straight to it, which worked only
     * because the page authorized nobody. Staff visibility into a student's
     * verbatim answers is a Phase 3 product decision that has not been made,
     * and an accidental hole must not be quietly converted into a deliberate
     * grant. Pinned so that granting it later is a choice someone makes on
     * purpose and has to change a test to express.
     */
    #[Test]
    public function organisation_staff_may_read_a_members_portrait_by_default(): void
    {
        [$student, $staff] = $this->schoolWith('mentor');

        $assessment = $this->ownedAssessment($student);

        $this->actingAs($staff)
            ->get("/assessment/{$assessment->id}/results")
            ->assertOk();
    }

    /**
     * The flag is the whole reason this is a setting and not a constant. An
     * organization that closes it closes it for its own staff immediately,
     * with no release and no migration.
     */
    #[Test]
    public function an_organisation_can_close_it(): void
    {
        [$student, $staff, $organization] = $this->schoolWith('mentor');

        $organization->update([
            'settings' => [Organization::SETTING_STAFF_MAY_READ_PORTRAITS => false],
        ]);

        $assessment = $this->ownedAssessment($student);

        $this->actingAs($staff)
            ->get("/assessment/{$assessment->id}/results")
            ->assertForbidden();
    }

    /**
     * The grant is about a relationship, not a role. Being a counsellor
     * somewhere is not being *this* student's counsellor.
     */
    #[Test]
    public function staff_of_another_school_are_still_strangers(): void
    {
        [$student] = $this->schoolWith('mentor');
        [, $elsewhere] = $this->schoolWith('mentor', slug: 'other-academy');

        $assessment = $this->ownedAssessment($student);

        $this->actingAs($elsewhere)
            ->get("/assessment/{$assessment->id}/results")
            ->assertForbidden();
    }

    /**
     * Sharing a roster is not staff. The most likely way to get this wrong is
     * to check membership and forget to check the role.
     */
    #[Test]
    public function a_classmate_is_not_staff(): void
    {
        [$student, , $organization] = $this->schoolWith('mentor');

        $classmate = User::factory()->create();
        $organization->users()->attach($classmate, ['id' => Str::uuid()->toString(), 'role' => 'member']);

        $assessment = $this->ownedAssessment($student);

        $this->actingAs($classmate)
            ->get("/assessment/{$assessment->id}/results")
            ->assertForbidden();
    }

    /**
     * Reading is not acting. A counsellor may open the portrait; they may not
     * answer as the student, tell us the portrait was wrong on the student's
     * behalf, or mail it to an address they type — which would otherwise be an
     * exfiltration path wearing a feature's clothes.
     */
    #[Test]
    public function reading_a_portrait_does_not_let_staff_act_as_the_student(): void
    {
        [$student, $staff] = $this->schoolWith('mentor');

        $assessment = $this->ownedAssessment($student);

        $this->actingAs($staff)
            ->post("/assessment/{$assessment->id}/feedback", [
                'question' => FeedbackQuestion::SoundsLikeMe->value,
                'standing' => FeedbackStanding::NotAtAll->value,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->postJson("/api/v1/assessments/{$assessment->id}/results/email", [
                'email' => 'somewhere-else@example.com',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->postJson("/api/v1/assessments/{$assessment->id}/answers", [
                'question_id' => Str::uuid()->toString(),
                'response_text' => 'Put this in their mouth.',
            ])
            ->assertForbidden();
    }

    /**
     * ⚠️ HARD INVARIANT. The flag governs the portrait and nothing else. The
     * coach and the brain are not reachable through it at any setting, which
     * is the boundary the whole product's trustworthiness rests on.
     */
    #[Test]
    public function the_grant_never_reaches_the_coach_or_the_brain(): void
    {
        [, $staff] = $this->schoolWith('mentor');

        // Staff get their own coach and their own brain if they have one.
        // There is no route that takes another person's identity at all —
        // which is the strongest form this guarantee can take, and the reason
        // this asserts on the route table rather than on a response.
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        foreach ($routes as $uri) {
            if (str_contains($uri, 'coach') || str_contains($uri, 'brain')) {
                $this->assertStringNotContainsString(
                    '{user}',
                    $uri,
                    "The route '{$uri}' takes another person as a parameter.",
                );
                $this->assertStringNotContainsString('{student}', $uri);
                $this->assertStringNotContainsString('{member}', $uri);
            }
        }

        $this->assertTrue($routes->contains('coach'), 'The coach route vanished; this test would pass vacuously.');
    }

    /**
     * @return array{User, User, Organization}
     */
    protected function schoolWith(string $role, string $slug = 'grace-academy'): array
    {
        $student = User::factory()->create();
        $staff = User::factory()->create();

        $organization = Organization::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'type' => 'school',
        ]);

        // The pivot carries a UUID primary key like every other table here.
        $organization->users()->attach($student, ['id' => Str::uuid()->toString(), 'role' => 'member']);
        $organization->users()->attach($staff, ['id' => Str::uuid()->toString(), 'role' => $role]);

        return [$student, $staff, $organization];
    }

    /**
     * The link in the results email is the copy most likely to still exist in
     * six months. It used to point at the JSON API, which authorizes by a
     * header a click from an inbox cannot send.
     */
    #[Test]
    public function the_emailed_link_opens_the_page_and_carries_the_token(): void
    {
        $assessment = $this->guestAssessment();

        $profile = VocationalProfile::create([
            'assessment_id' => $assessment->id,
            'opening_synthesis' => 'You are drawn to creative problem-solving.',
            'primary_pathways' => [],
            'next_steps' => [],
            'category_scores' => [],
            'ai_analysis_raw' => [],
        ]);

        $url = (new ResultsMail($profile))->content()->with['resultsUrl'];

        $this->assertStringNotContainsString('/api/', $url);
        $this->assertStringContainsString("/assessment/{$assessment->id}/results", $url);
        $this->assertStringContainsString(AssessmentAccess::TOKEN_PARAM.'='.$assessment->guest_token, urldecode($url));

        $this->get($url)->assertOk();
    }
}
