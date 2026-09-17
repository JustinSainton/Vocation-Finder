<?php

namespace Tests\Feature;

use App\Enums\BrainEntrySource;
use App\Models\Action;
use App\Models\BrainEntry;
use App\Models\User;
use App\Support\BrainstormSchedule;
use App\Support\ThresholdSurfacing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 2.4 — scheduled brainstorms, monthly by default, adaptive.
 *
 * The cadence is a ceiling on contact, not a schedule of it. It moves on
 * evidence of what the student does with an invitation and never on a
 * marketing rhythm, because a notification a sixteen-year-old has learned to
 * ignore takes the one that mattered down with it.
 */
class BrainstormScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function studentSince(string $on): User
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => CarbonImmutable::parse($on)])->save();

        return $user->fresh();
    }

    /**
     * Inviting someone to brainstorm on the day they finished the assessment
     * is the product talking rather than listening.
     */
    #[Test]
    public function a_student_who_just_arrived_is_not_due(): void
    {
        $user = $this->studentSince('2026-05-01');

        $this->assertFalse((new BrainstormSchedule)->isDue($user, CarbonImmutable::parse('2026-05-02')));
        $this->assertNull((new BrainstormSchedule)->invitation($user, CarbonImmutable::parse('2026-05-02')));
    }

    #[Test]
    public function the_default_is_monthly(): void
    {
        $user = $this->studentSince('2026-05-01');
        $schedule = new BrainstormSchedule;

        $this->assertSame(BrainstormSchedule::DEFAULT_CADENCE_DAYS, $schedule->for($user)->cadence_days);
        $this->assertFalse($schedule->isDue($user, CarbonImmutable::parse('2026-05-29')));
        $this->assertTrue($schedule->isDue($user, CarbonImmutable::parse('2026-06-01')));
    }

    /**
     * An invitation with nothing in it is a nag. When the brain has heard the
     * student repeat something, that is what the session opens with — their
     * own words, never a generated topic.
     */
    #[Test]
    public function the_invitation_opens_with_what_they_already_said(): void
    {
        $user = $this->studentSince('2026-02-01');

        foreach ([
            ['I liked rewiring the lamp in the garage.', '2026-03-02'],
            ['Spent Saturday rewiring an old amp.', '2026-03-28'],
            ['The rewiring stuff is the only homework I finish.', '2026-04-20'],
            ['Asked about rewiring the stage lights.', '2026-05-11'],
        ] as [$content, $on]) {
            BrainEntry::create([
                'user_id' => $user->id,
                'content' => $content,
                'source' => BrainEntrySource::Coach,
                'occurred_at' => CarbonImmutable::parse($on),
            ]);
        }

        $invitation = (new BrainstormSchedule)->invitation($user, CarbonImmutable::parse('2026-05-20'));

        $this->assertNotNull($invitation);
        $this->assertSame('rewiring', $invitation['opens_with']['term']);
        $this->assertStringContainsString('4 times', $invitation['prompt']);
        $this->assertStringContainsString('Is it still true?', $invitation['prompt']);
    }

    /**
     * ⚠️ Crisis reaches a person before it reaches vocational meaning. A
     * pattern built from distress must never become a brainstorm topic.
     */
    #[Test]
    public function distress_never_becomes_the_topic_of_a_brainstorm(): void
    {
        $user = $this->studentSince('2026-02-01');

        foreach ([
            ['It all feels pretty hopeless lately.', '2026-03-02'],
            ['Still hopeless about the whole thing.', '2026-03-28'],
            ['I said hopeless again to my friend.', '2026-04-20'],
            ['Hopeless is the word that keeps coming out.', '2026-05-11'],
        ] as [$content, $on]) {
            BrainEntry::create([
                'user_id' => $user->id,
                'content' => $content,
                'source' => BrainEntrySource::Coach,
                'occurred_at' => CarbonImmutable::parse($on),
            ]);
        }

        // The detector still sees it — that is how it reaches a person.
        $this->assertTrue(
            (new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-05-20'))['needs_human'],
        );

        $invitation = (new BrainstormSchedule)->invitation($user, CarbonImmutable::parse('2026-05-20'));

        $this->assertNull($invitation['opens_with'], 'Distress was about to be handed back as a brainstorm topic.');
        $this->assertStringNotContainsStringIgnoringCase('hopeless', $invitation['prompt']);
    }

    /**
     * Someone who is moving gets asked back sooner, because they want it.
     */
    #[Test]
    public function a_student_who_is_moving_is_asked_back_sooner(): void
    {
        $user = $this->studentSince('2026-02-01');
        $now = CarbonImmutable::parse('2026-05-20');

        Action::create([
            'user_id' => $user->id,
            'title' => 'Email the shop teacher about the stage lights.',
            'status' => 'completed',
            'assigned_at' => $now->subDays(20),
            'settled_at' => $now->subDays(10),
        ]);

        $schedule = (new BrainstormSchedule)->attended($user, $now);

        $this->assertSame(BrainstormSchedule::ENGAGED_CADENCE_DAYS, $schedule->cadence_days);
    }

    /**
     * And someone who is not gets asked back later, and then later again.
     * Doubling rather than incrementing: the information in a second ignored
     * invitation is not "ask again tomorrow", it is that the rhythm is wrong.
     */
    #[Test]
    public function ignored_invitations_back_the_system_off(): void
    {
        $user = $this->studentSince('2026-02-01');
        $schedule = new BrainstormSchedule;
        $now = CarbonImmutable::parse('2026-05-20');

        $this->assertSame(60, $schedule->declined($user, $now)->cadence_days);
        $this->assertSame(90, $schedule->declined($user, $now)->cadence_days);
        $this->assertSame(90, $schedule->declined($user, $now)->cadence_days, 'The cadence has a floor on contact.');
    }

    /**
     * ⚠️ It never stops entirely. The brain is never deleted and neither is
     * the standing offer to come back to it — a cap on frequency is not a
     * countdown to abandonment.
     */
    #[Test]
    public function the_invitation_never_stops_altogether(): void
    {
        $user = $this->studentSince('2026-01-01');
        $schedule = new BrainstormSchedule;
        $now = CarbonImmutable::parse('2026-05-20');

        foreach (range(1, 10) as $ignored) {
            $schedule->declined($user, $now);
        }

        $this->assertLessThanOrEqual(BrainstormSchedule::MAX_CADENCE_DAYS, $schedule->for($user)->cadence_days);
        $this->assertTrue($schedule->isDue($user, $now->addDays(BrainstormSchedule::MAX_CADENCE_DAYS + 1)));
    }

    /**
     * Showing up clears the backing-off. Someone returning after six months
     * is not still being treated as someone who ignores invitations.
     */
    #[Test]
    public function coming_back_resets_the_rhythm(): void
    {
        $user = $this->studentSince('2026-01-01');
        $schedule = new BrainstormSchedule;
        $now = CarbonImmutable::parse('2026-05-20');

        $schedule->declined($user, $now);
        $schedule->declined($user, $now);

        $after = $schedule->attended($user, $now);

        $this->assertSame(0, $after->consecutive_declines);
        $this->assertSame(BrainstormSchedule::DEFAULT_CADENCE_DAYS, $after->cadence_days);
    }
}
