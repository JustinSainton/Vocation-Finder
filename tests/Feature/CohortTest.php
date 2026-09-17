<?php

namespace Tests\Feature;

use App\Enums\CohortSignal;
use App\Enums\GapStatus;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Assessment;
use App\Models\BrainEntry;
use App\Models\Gap;
use App\Models\Organization;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\CohortView;
use App\Support\ParentVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The cohort surface, and the two things it must never become.
 *
 * It must not become a ranking, and it must not become a window into the
 * coaching. Both are asserted against the payload the server actually sends,
 * because a page can look restrained while shipping the material that lets
 * somebody build the ranking themselves.
 */
class CohortTest extends TestCase
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

    protected function join(Organization $organization, User $user, string $role): void
    {
        $organization->users()->attach($user, ['id' => Str::uuid()->toString(), 'role' => $role]);
    }

    /**
     * A student in this school who has finished their assessment and has a
     * parent's yes on file — the baseline everything else is a deviation from.
     */
    protected function student(Organization $organization, string $name, bool $started = true): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);
        $this->join($organization, $user, 'member');

        ParentConsent::create([
            'user_id' => $user->id,
            'parent_name' => 'A parent',
            'parent_email' => Str::slug($name).'-parent@example.com',
        ])->grant();

        if ($started) {
            Assessment::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'mode' => 'written',
                'status' => 'completed',
                'started_at' => now()->subMonths(2),
                'completed_at' => now()->subMonth(),
            ]);
        }

        return $user->fresh();
    }

    /**
     * @return list<string>
     */
    protected function keysIn(mixed $payload, array $found = []): array
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

    protected function groupFor(array $cohort, CohortSignal $signal): array
    {
        return collect($cohort['groups'])->firstWhere('key', $signal->value);
    }

    #[Test]
    public function a_student_waiting_on_a_parent_is_the_first_thing_the_page_says(): void
    {
        $school = $this->school();

        $waiting = User::factory()->create([
            'name' => 'Waiting',
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);
        $this->join($school, $waiting, 'member');

        $cohort = (new CohortView)->for($school);

        $this->assertSame(CohortSignal::NeedsConsent->value, $cohort['groups'][0]['key']);
        $this->assertSame(['Waiting'], array_column($this->groupFor($cohort, CohortSignal::NeedsConsent)['students'], 'name'));
    }

    #[Test]
    public function a_student_who_has_not_finished_the_assessment_is_named(): void
    {
        $school = $this->school();
        $this->student($school, 'Not Started', started: false);

        $cohort = (new CohortView)->for($school);

        $this->assertSame(['Not Started'], array_column($this->groupFor($cohort, CohortSignal::NotStarted)['students'], 'name'));
    }

    /**
     * A student appearing under four headings is a list nobody reads, and the
     * first signal is the binding constraint anyway.
     */
    #[Test]
    public function a_student_appears_exactly_once(): void
    {
        $school = $this->school();
        $student = $this->student($school, 'Everything At Once', started: false);

        Gap::create([
            'user_id' => $student->id,
            'type' => GapType::Finances,
            'status' => GapStatus::Open,
            'summary' => 'Cannot pay the application fees.',
            'source' => 'assessment',
        ]);

        Action::create([
            'user_id' => $student->id,
            'title' => 'Sit in on one nursing class.',
            'assigned_at' => now()->subMonths(2),
        ]);

        $cohort = (new CohortView)->for($school);

        $appearances = collect($cohort['groups'])
            ->flatMap(fn (array $group) => array_column($group['students'], 'name'))
            ->filter(fn (string $name) => $name === 'Everything At Once');

        $this->assertCount(1, $appearances);
    }

    #[Test]
    public function a_gap_a_school_can_fix_outranks_a_gap_it_cannot(): void
    {
        $school = $this->school();
        $outlook = $this->student($school, 'Outlook');
        $money = $this->student($school, 'Money');

        Gap::create([
            'user_id' => $outlook->id,
            'type' => GapType::FutureOutlook,
            'status' => GapStatus::Open,
            'summary' => 'Does not believe it is possible for them.',
            'source' => 'assessment',
        ]);

        Gap::create([
            'user_id' => $money->id,
            'type' => GapType::Finances,
            'status' => GapStatus::Open,
            'summary' => 'Cannot pay the application fees.',
            'source' => 'assessment',
        ]);

        $cohort = (new CohortView)->for($school);
        $blocked = $this->groupFor($cohort, CohortSignal::Blocked);

        $this->assertSame(['Money'], array_column($blocked['students'], 'name'));
        $this->assertSame(GapType::Finances->label(), $blocked['students'][0]['detail']);
        $this->assertContains('Outlook', array_column($this->groupFor($cohort, CohortSignal::Moving)['students'], 'name'));
    }

    #[Test]
    public function a_step_that_has_sat_for_a_fortnight_is_worth_a_conversation(): void
    {
        $school = $this->school();
        $stuck = $this->student($school, 'Stuck');
        $fresh = $this->student($school, 'Fresh');

        /*
         * Literal days, not `STALLED_AFTER_DAYS ± 1`. Dating the fixtures off
         * the constant makes the test move whenever the constant does, so
         * widening the window to a whole term would still pass — the test
         * would be asserting that the code agrees with itself.
         */
        Action::create([
            'user_id' => $stuck->id,
            'title' => 'Ask your aunt about the ICU.',
            'assigned_at' => now()->subDays(20),
        ]);

        Action::create([
            'user_id' => $fresh->id,
            'title' => 'Ask your aunt about the ICU.',
            'assigned_at' => now()->subDays(5),
        ]);

        $cohort = (new CohortView)->for($school);

        $this->assertSame(['Stuck'], array_column($this->groupFor($cohort, CohortSignal::Stalled)['students'], 'name'));
        $this->assertContains('Fresh', array_column($this->groupFor($cohort, CohortSignal::Moving)['students'], 'name'));
    }

    /**
     * The flag is only useful if it says what to do about it. A counsellor
     * handed a list of stuck names and no move has been given a way to feel
     * informed, which is not the same as a way to help.
     */
    #[Test]
    public function every_group_arrives_with_a_move_attached(): void
    {
        $cohort = (new CohortView)->for($this->school());

        foreach ($cohort['groups'] as $group) {
            $this->assertNotEmpty($group['move']);
        }
    }

    /**
     * The one the whole surface exists to not become.
     */
    #[Test]
    public function nobody_in_the_cohort_carries_a_number(): void
    {
        $school = $this->school();
        $student = $this->student($school, 'Measured');

        Action::create([
            'user_id' => $student->id,
            'title' => 'Ask your aunt about the ICU.',
            'assigned_at' => now()->subMonths(2),
        ]);

        $cohort = (new CohortView)->for($school);

        foreach ($cohort['groups'] as $group) {
            foreach ($group['students'] as $listed) {
                foreach (['score', 'percent', 'percentage', 'rate', 'rank', 'level', 'readiness', 'progress', 'position'] as $forbidden) {
                    $this->assertArrayNotHasKey($forbidden, $listed);
                }
            }
        }

        $this->assertNotContains('readiness', $this->keysIn($cohort));
    }

    /**
     * The same boundary as the parents', reused rather than restated. Staff
     * are closer to the student than a parent is in some ways and further in
     * others, but the line is in the same place: nothing the student said.
     */
    #[Test]
    public function nothing_the_student_said_reaches_their_school(): void
    {
        $school = $this->school();
        $student = $this->student($school, 'Private');

        Gap::create([
            'user_id' => $student->id,
            'type' => GapType::Finances,
            'status' => GapStatus::Open,
            'summary' => 'PRIVATESUMMARY cannot pay the application fees.',
            'evidence' => 'PRIVATEEVIDENCE my mom would kill me if she knew.',
            'source' => 'assessment',
        ]);

        BrainEntry::create([
            'user_id' => $student->id,
            'source' => 'coach',
            'content' => 'PRIVATEBRAIN I think I am doing this for my mom.',
            'context' => 'coach conversation',
            'occurred_at' => now(),
        ]);

        $cohort = (new CohortView)->for($school);
        $keys = $this->keysIn($cohort);

        foreach (ParentVisibility::FORBIDDEN_KEYS as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "The cohort payload carries a `{$forbidden}` key.");
        }

        $encoded = json_encode($cohort);

        foreach (['PRIVATESUMMARY', 'PRIVATEEVIDENCE', 'PRIVATEBRAIN'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
    }

    #[Test]
    public function the_page_reports_seats_so_a_roster_can_be_managed(): void
    {
        $school = $this->school();
        $admin = User::factory()->create();
        $this->join($school, $admin, 'admin');
        $this->student($school, 'One');

        $school->invitations()->create([
            'email' => 'invited@example.com',
            'role' => 'member',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $cohort = (new CohortView)->for($school);

        $this->assertSame(2, $cohort['seats']['used']);
        $this->assertSame(1, $cohort['seats']['pending']);
        $this->assertSame($school->memberLimit(), $cohort['seats']['limit']);
    }

    #[Test]
    public function a_counsellor_may_read_the_cohort_and_a_stranger_may_not(): void
    {
        $school = $this->school();
        $mentor = User::factory()->create();
        $this->join($school, $mentor, 'mentor');
        $this->student($school, 'One');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/org/{$school->slug}/cohort")->assertForbidden();

        $this->actingAs($mentor)
            ->get("/org/{$school->slug}/cohort")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Org/Cohort'));
    }

    /**
     * A student of another school is another school's business.
     */
    #[Test]
    public function the_cohort_stops_at_the_edge_of_the_organisation(): void
    {
        $school = $this->school();
        $this->student($school, 'Ours');

        $other = Organization::create(['name' => 'Elsewhere', 'slug' => 'elsewhere', 'type' => 'school']);
        $this->student($other, 'Theirs');

        $names = collect((new CohortView)->for($school)['groups'])
            ->flatMap(fn (array $group) => array_column($group['students'], 'name'));

        $this->assertContains('Ours', $names);
        $this->assertNotContains('Theirs', $names);
    }
}
