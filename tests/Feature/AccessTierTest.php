<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Tools\AssignActionTool;
use App\Ai\Tools\PrescribeHabitTool;
use App\Ai\Tools\SearchBrainTool;
use App\Enums\AgeTier;
use App\Enums\ConsentStatus;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Gap;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\AccessPolicy;
use App\Support\ParentVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Age gate, parent consent, and the privacy boundary that goes with them.
 *
 * Two rules here are product invariants rather than settings: a freshman does
 * not get the coach at any price, and a parent never sees a coaching
 * conversation. Both are asserted against the code that enforces them, not
 * against a UI that hides them.
 */
class AccessTierTest extends TestCase
{
    use RefreshDatabase;

    protected function student(?int $grade, ?string $birthdate = null): User
    {
        return User::factory()->create([
            'grade_level' => $grade,
            'birthdate' => $birthdate,
        ]);
    }

    protected function consentedJunior(): User
    {
        $student = $this->student(11, now()->subYears(16)->toDateString());
        ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ])->grant('203.0.113.7');

        return $student->fresh();
    }

    /**
     * @return array<string, array{0: int|null, 1: string|null, 2: string}>
     */
    public static function tierCases(): array
    {
        return [
            'freshman' => [9, null, AgeTier::FreshmanSophomore->value],
            'sophomore' => [10, null, AgeTier::FreshmanSophomore->value],
            'junior' => [11, null, AgeTier::JuniorSenior->value],
            'senior' => [12, null, AgeTier::JuniorSenior->value],
            'adult by birthdate' => [null, '2000-01-01', AgeTier::Adult->value],
            'adult still enrolled' => [12, '2000-01-01', AgeTier::Adult->value],
        ];
    }

    #[DataProvider('tierCases')]
    public function test_it_places_a_student_in_the_right_tier(?int $grade, ?string $birthdate, string $expected): void
    {
        $this->assertSame($expected, AccessPolicy::tier($this->student($grade, $birthdate))->value);
    }

    /**
     * A sixteen-year-old may be a sophomore or a junior, so grade decides the
     * school bands and age decides adulthood. Deriving one from the other
     * misplaces held-back, skipped and homeschooled students.
     */
    public function test_grade_decides_the_school_bands_independently_of_age(): void
    {
        $sixteen = now()->subYears(16)->toDateString();

        $this->assertSame(AgeTier::FreshmanSophomore, AccessPolicy::tier($this->student(10, $sixteen)));
        $this->assertSame(AgeTier::JuniorSenior, AccessPolicy::tier($this->student(11, $sixteen)));
    }

    public function test_it_falls_back_to_age_when_grade_is_unknown(): void
    {
        $this->assertSame(AgeTier::JuniorSenior, AccessPolicy::tier($this->student(null, now()->subYears(17)->toDateString())));
        $this->assertSame(AgeTier::FreshmanSophomore, AccessPolicy::tier($this->student(null, now()->subYears(14)->toDateString())));
    }

    /**
     * The costs are not symmetric: guessing "adult" for an unknown birthdate
     * would skip a consent step for someone who needed it.
     */
    public function test_a_student_with_no_age_information_is_treated_as_a_minor(): void
    {
        $unknown = $this->student(null, null);

        $this->assertFalse(AccessPolicy::isAdult($unknown));
        $this->assertTrue(AccessPolicy::tier($unknown)->isMinor());
    }

    /**
     * A policy gate, not a paywall. No subscription state opens this.
     */
    public function test_a_freshman_cannot_use_the_coach_at_any_price(): void
    {
        $freshman = $this->student(9);

        $this->assertFalse(AccessPolicy::canUseCoach($freshman));
        $this->assertFalse(AccessPolicy::canUseBrain($freshman));
        $this->assertFalse(AccessPolicy::requiresParentCheckout($freshman));
        $this->assertStringContainsString('opens in junior year', AccessPolicy::coachBlockedReason($freshman));
    }

    public function test_a_junior_without_consent_cannot_use_the_coach(): void
    {
        $junior = $this->student(11);

        $this->assertTrue(AccessPolicy::requiresParentConsent($junior));
        $this->assertFalse(AccessPolicy::canUseCoach($junior));
        $this->assertStringContainsString('parent or guardian', AccessPolicy::coachBlockedReason($junior));
    }

    /**
     * Juniors and seniors get the full coach, not a limited one. Withholding
     * capability at the decision moment teaches a student the tool is not for
     * them.
     */
    public function test_a_consented_junior_gets_the_full_coach(): void
    {
        $junior = $this->consentedJunior();

        $this->assertTrue(AccessPolicy::canUseCoach($junior));
        $this->assertTrue(AccessPolicy::canUseBrain($junior));
        $this->assertNull(AccessPolicy::coachBlockedReason($junior));
        /*
         * Named rather than counted. A bare count says nothing about which
         * capability was withheld, and it has to be edited every time the
         * coach gains a tool — which makes it a chore rather than a guard.
         * The exact toolset is pinned once, in PathwayCoachAgentTest; what
         * matters here is that entitlement did not quietly strip the
         * capabilities a "limited" coach would lose first.
         */
        $tools = collect((new PathwayCoachAgent($junior))->tools())
            ->map(fn ($tool) => $tool::class)
            ->all();

        foreach ([AssignActionTool::class, SearchBrainTool::class, PrescribeHabitTool::class] as $capability) {
            $this->assertContains($capability, $tools, 'A consented junior was given a limited coach.');
        }
    }

    public function test_an_adult_needs_no_parent_involvement_of_any_kind(): void
    {
        $adult = $this->student(null, '2000-01-01');

        $this->assertFalse(AccessPolicy::requiresParentConsent($adult));
        $this->assertFalse(AccessPolicy::requiresParentCheckout($adult));
        $this->assertFalse(AccessPolicy::permitsParentReporting($adult));
        $this->assertTrue(AccessPolicy::canUseCoach($adult));
    }

    /**
     * The guard lives in the agent because that is the one place every caller
     * passes through. A route that forgets to check must still fail closed.
     */
    public function test_the_coach_refuses_to_exist_for_an_unentitled_student(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('opens in junior year');

        new PathwayCoachAgent($this->student(9));
    }

    public function test_the_coach_refuses_a_junior_whose_parent_has_not_consented(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('parent or guardian');

        new PathwayCoachAgent($this->student(11));
    }

    public function test_a_pending_consent_is_not_a_granted_one(): void
    {
        $junior = $this->student(11);
        ParentConsent::create([
            'user_id' => $junior->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $this->assertFalse(AccessPolicy::hasParentConsent($junior));
        $this->assertFalse(AccessPolicy::canUseCoach($junior));
    }

    /**
     * A parent may withdraw consent at any time. That stops the coach; it does
     * not delete anything the student built.
     */
    public function test_revoking_consent_stops_the_coach_without_destroying_anything(): void
    {
        $junior = $this->consentedJunior();
        Gap::create(['user_id' => $junior->id, 'type' => GapType::Access, 'summary' => 'No way in yet.']);

        $junior->parentConsents()->first()->revoke();

        $this->assertFalse(AccessPolicy::canUseCoach($junior->fresh()));
        $this->assertSame(1, $junior->gaps()->count());
        $this->assertSame(ConsentStatus::Revoked, $junior->parentConsents()->first()->status);
    }

    public function test_consent_generates_a_token_and_keeps_it_out_of_serialization(): void
    {
        $consent = ParentConsent::create([
            'user_id' => $this->student(11)->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ]);

        $this->assertSame(64, strlen($consent->token));
        $this->assertArrayNotHasKey('token', $consent->toArray());
    }

    public function test_granting_records_when_and_from_where(): void
    {
        $junior = $this->consentedJunior();
        $consent = $junior->parentConsents()->first();

        $this->assertTrue($consent->isGranted());
        $this->assertNotNull($consent->granted_at);
        $this->assertSame('203.0.113.7', $consent->granted_ip);
    }

    /**
     * THE HARD INVARIANT. "A student who knows their parent is reading
     * everything will not be honest with the coach, and the honesty is what
     * the whole thing runs on."
     */
    public function test_a_parent_summary_never_contains_the_students_own_words(): void
    {
        $junior = $this->consentedJunior();

        $gap = Gap::create([
            'user_id' => $junior->id,
            'type' => GapType::Relationships,
            'summary' => 'Nobody they know does this work.',
            'evidence' => 'I do not really know anyone who does that.',
        ]);

        Action::create([
            'user_id' => $junior->id,
            'gap_id' => $gap->id,
            'title' => 'Ask your aunt what her hardest week looks like.',
            'reflection' => 'I was too nervous to ask the real question.',
        ]);

        $encoded = json_encode(ParentVisibility::summaryFor($junior));

        $this->assertStringNotContainsString('I do not really know anyone', $encoded);
        $this->assertStringNotContainsString('too nervous', $encoded);

        foreach (ParentVisibility::FORBIDDEN_KEYS as $key) {
            $this->assertArrayNotHasKey($key, ParentVisibility::summaryFor($junior));
        }
    }

    /**
     * The boundary is finer than "hide the chat": a milestone is shown, the
     * student's private reflection on it is not.
     */
    public function test_a_parent_sees_the_milestone_but_not_the_reflection(): void
    {
        $junior = $this->consentedJunior();

        Action::create([
            'user_id' => $junior->id,
            'title' => 'Ask your aunt what her hardest week looks like.',
            'reflection' => 'I chickened out.',
        ]);

        $summary = ParentVisibility::summaryFor($junior);

        $this->assertSame('Ask your aunt what her hardest week looks like.', $summary['current_action']);
        $this->assertStringNotContainsString('chickened out', json_encode($summary));
    }

    /**
     * The vision's mechanism for involving a family is giving them the
     * question to ask, not a window into the coaching.
     */
    public function test_a_parent_is_given_something_specific_to_do(): void
    {
        $junior = $this->consentedJunior();

        Action::create([
            'user_id' => $junior->id,
            'title' => 'Ask your aunt what her hardest week looks like.',
        ]);

        $summary = ParentVisibility::summaryFor($junior);

        /*
         * The step is named in the question the parent is handed. Its own full
         * stop is trimmed because a prompt quotes the title mid-sentence — a
         * parent should not read "…what the hardest part of "do the thing." is".
         */
        $this->assertStringContainsString(
            'Ask your aunt what her hardest week looks like',
            $summary['suggested_conversation'],
        );
        $this->assertSame('Ask your aunt what her hardest week looks like.', $summary['current_action']);
    }

    public function test_a_parent_gets_a_conversation_starter_even_with_no_action_yet(): void
    {
        $summary = ParentVisibility::summaryFor($this->consentedJunior());

        $this->assertNull($summary['current_action']);
        $this->assertNotSame('', $summary['suggested_conversation']);
    }

    public function test_there_is_no_parent_reporting_for_an_adult(): void
    {
        $summary = ParentVisibility::summaryFor($this->student(null, '2000-01-01'));

        $this->assertFalse($summary['reportable']);
        $this->assertArrayNotHasKey('current_action', $summary);
        $this->assertArrayNotHasKey('student_name', $summary);
    }

    public function test_it_reports_progress_counts_to_a_parent(): void
    {
        $junior = $this->consentedJunior();

        $action = Action::create([
            'user_id' => $junior->id,
            'title' => 'Ask your aunt what her hardest week looks like.',
        ]);
        $action->complete('It went fine.');

        $summary = ParentVisibility::summaryFor($junior);

        $this->assertSame(1, $summary['actions_completed']);
        $this->assertStringNotContainsString('It went fine', json_encode($summary));
    }
}
