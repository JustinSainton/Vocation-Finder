<?php

namespace Tests\Feature;

use App\Models\BrainEntry;
use App\Models\User;
use App\Support\BrainstormSchedule;
use App\Support\Nudges\Nudge;
use App\Support\Nudges\NudgeChannel;
use App\Support\Nudges\NudgeDispatcher;
use App\Support\Nudges\SilentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The nudge seam, which has no carrier behind it yet.
 *
 * Roadmap 3.6 was deferred rather than built: the likely first driver is a
 * push notification or an iOS Live Activity through the Expo app, not SMS.
 * What is settled and worth pinning now is the *rules* — which are product
 * decisions, not provider ones. These tests exist so the person who adds a
 * real carrier inherits the argument already won.
 */
class NudgeChannelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A channel that records what it was handed, standing in for a real one.
     */
    protected function recordingChannel(): NudgeChannel
    {
        return new class implements NudgeChannel
        {
            /** @var list<Nudge> */
            public array $sent = [];

            public function canReach(User $user): bool
            {
                return true;
            }

            public function deliver(User $user, Nudge $nudge): void
            {
                $this->sent[] = $nudge;
            }
        };
    }

    protected function useChannel(NudgeChannel $channel): void
    {
        $this->app->instance($channel::class, $channel);

        config()->set('vocation.nudges.channel', 'test');
        config()->set('vocation.nudges.channels.test', $channel::class);
    }

    /**
     * An entitled student who is due, with something of their own to open on.
     */
    protected function studentWhoIsDue(): User
    {
        /*
         * Backdated, because a student is deliberately not due the day they
         * sign up — inviting somebody to brainstorm on the day they finished
         * the assessment is the product talking rather than listening.
         */
        $student = User::factory()->create([
            'birthdate' => now()->subYears(19),
            'created_at' => now()->subMonths(4),
        ]);

        BrainEntry::create([
            'user_id' => $student->id,
            'source' => 'coach',
            'content' => 'I keep thinking about the hospital.',
            'context' => 'coach conversation',
            'occurred_at' => now()->subMonths(3),
        ]);

        return $student->fresh();
    }

    /**
     * The one that makes the whole stub safe to leave in the tree.
     */
    #[Test]
    public function nothing_is_configured_and_so_nothing_is_sent(): void
    {
        $student = $this->studentWhoIsDue();

        $this->assertInstanceOf(SilentChannel::class, (new NudgeDispatcher)->channel());
        $this->assertNull((new NudgeDispatcher)->nudge($student));
    }

    /**
     * An unknown name falls back to silence rather than to whichever driver
     * happens to be registered. A stub must not fail open.
     */
    #[Test]
    public function an_unrecognised_channel_name_is_silence_not_a_guess(): void
    {
        /*
         * A working channel has to be registered for this to mean anything.
         * `silent` is taken *out* of the list too: with it registered, code
         * that fell through to "the first driver registered" would land on
         * silence by accident and look correct.
         */
        $channel = $this->recordingChannel();
        $this->app->instance($channel::class, $channel);
        config()->set('vocation.nudges.channels', ['test' => $channel::class]);
        config()->set('vocation.nudges.channel', 'carrier-pigeon');

        $this->assertInstanceOf(SilentChannel::class, (new NudgeDispatcher)->channel());
    }

    /**
     * Anything named here has to actually be a channel. A misconfigured class
     * reaching `deliver()` would be a fatal error in a scheduled job, at
     * night, on somebody else's machine.
     */
    #[Test]
    public function a_class_that_is_not_a_channel_is_refused(): void
    {
        config()->set('vocation.nudges.channel', 'bogus');
        config()->set('vocation.nudges.channels.bogus', User::class);

        $this->assertInstanceOf(SilentChannel::class, (new NudgeDispatcher)->channel());
    }

    #[Test]
    public function a_student_who_is_due_is_invited_in_their_own_material(): void
    {
        $channel = $this->recordingChannel();
        $this->useChannel($channel);

        $nudge = (new NudgeDispatcher)->nudge($this->studentWhoIsDue());

        $this->assertNotNull($nudge);
        $this->assertNotEmpty($nudge->body);
        $this->assertCount(1, $channel->sent);
    }

    /**
     * An invitation with nothing in it is a nag. When the schedule declines to
     * give a reason, the dispatcher may not manufacture one.
     */
    #[Test]
    public function a_student_who_is_not_due_hears_nothing(): void
    {
        $channel = $this->recordingChannel();
        $this->useChannel($channel);

        $student = $this->studentWhoIsDue();

        $this->assertNotNull((new NudgeDispatcher)->nudge($student));
        $this->assertCount(1, $channel->sent);

        /*
         * The second run is the one that matters. A dispatcher that sent
         * without recording would fire again every time the job ran, which is
         * the difference between an invitation and a nag.
         */
        $this->assertNull((new NudgeDispatcher)->nudge($student->fresh()));
        $this->assertCount(1, $channel->sent);
    }

    /**
     * 2.4 says an ignored invitation means the rhythm is wrong, not that we
     * should ask again tomorrow. Nothing in the application called
     * `declined()` before this dispatcher existed, so the backing-off half of
     * that rule had never had a caller.
     */
    #[Test]
    public function an_invitation_nobody_answered_widens_the_rhythm(): void
    {
        $this->useChannel($this->recordingChannel());

        $student = $this->studentWhoIsDue();
        $before = (new BrainstormSchedule)->for($student)->cadence_days;

        (new NudgeDispatcher)->nudge($student);

        $after = (new BrainstormSchedule)->for($student->fresh())->cadence_days;

        $this->assertGreaterThan($before, $after);
    }

    /**
     * A student the coach is closed to is never contacted about it, and no
     * schedule row is touched on their behalf.
     */
    #[Test]
    public function an_unentitled_student_is_never_reached(): void
    {
        $channel = $this->recordingChannel();
        $this->useChannel($channel);

        $freshman = User::factory()->create([
            'grade_level' => 9,
            'birthdate' => now()->subYears(14)->toDateString(),
        ]);

        $this->assertNull((new NudgeDispatcher)->nudge($freshman));
        $this->assertSame([], $channel->sent);
        $this->assertDatabaseCount('brainstorm_schedules', 0);
    }

    /**
     * A channel that cannot reach somebody is not an error and not a fallback
     * to some other channel. It is silence.
     */
    #[Test]
    public function a_channel_that_cannot_reach_the_student_sends_nothing(): void
    {
        $channel = new class implements NudgeChannel
        {
            public array $sent = [];

            public function canReach(User $user): bool
            {
                return false;
            }

            public function deliver(User $user, Nudge $nudge): void
            {
                $this->sent[] = $nudge;
            }
        };

        $this->useChannel($channel);

        $this->assertNull((new NudgeDispatcher)->nudge($this->studentWhoIsDue()));
        $this->assertSame([], $channel->sent);
    }

    /**
     * The channel decides how to carry a sentence, never when to speak. Two
     * things deciding when to contact a teenager is how a product starts
     * nagging, so the interface gives a driver nowhere to put a schedule.
     */
    #[Test]
    public function the_channel_contract_has_no_opinion_about_timing(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $method) => $method->getName(),
            (new \ReflectionClass(NudgeChannel::class))->getMethods(),
        );

        sort($methods);

        $this->assertSame(['canReach', 'deliver'], $methods);
    }
}
