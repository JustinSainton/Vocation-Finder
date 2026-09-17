<?php

namespace Tests\Feature;

use App\Enums\GapType;
use App\Enums\HabitCadence;
use App\Enums\HabitStanding;
use App\Enums\HabitStatus;
use App\Models\Gap;
use App\Models\Habit;
use App\Models\User;
use App\Support\HabitTracker;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Roadmap 1.5 — habits, and the one thing that makes this different from
 * every habit app a student has already deleted.
 *
 * A streak's mechanic is that breaking it destroys something. Applied to a
 * sixteen-year-old's vocational formation that produces shame and, worse,
 * inaccurate self-reports — and the accurate report is the only thing the
 * coach can act on. So: no streaks, no percentages shown to the student, and
 * a miss they explained never counts against them.
 */
class HabitTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function student(): User
    {
        return User::factory()->create();
    }

    protected function gapFor(User $user, GapType $type = GapType::Habits): Gap
    {
        return Gap::create([
            'user_id' => $user->id,
            'type' => $type,
            'summary' => 'They have never tried doing the thing on an ordinary day.',
        ]);
    }

    protected function habitFor(User $user, HabitCadence $cadence = HabitCadence::Daily, ?CarbonImmutable $started = null): Habit
    {
        $habit = (new HabitTracker)->prescribe(
            $user,
            $this->gapFor($user),
            'Write down one thing you noticed about the work you did that day.',
            $cadence,
            'So the next conversation has something real in it.',
        );

        if ($started) {
            $habit->forceFill(['started_at' => $started])->save();
        }

        return $habit->fresh();
    }

    /**
     * The roadmap's actual constraint: "tied to coach-prescribed habits (not
     * generic)". Enforced in the signature and the schema, not in a sentence
     * of the prompt that a model can drift away from.
     */
    #[Test]
    public function a_habit_cannot_exist_without_the_gap_it_serves(): void
    {
        $student = $this->student();

        $this->expectException(QueryException::class);

        Habit::create([
            'user_id' => $student->id,
            'cadence' => HabitCadence::Daily,
            'title' => 'Read something about engineering.',
        ]);
    }

    #[Test]
    public function a_habit_for_someone_elses_gap_is_refused(): void
    {
        $student = $this->student();
        $someoneElse = $this->student();

        $this->expectException(RuntimeException::class);

        (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($someoneElse),
            'Read something about engineering.',
            HabitCadence::Daily,
        );
    }

    /**
     * A plan repeated daily is the decision friction this product exists to
     * remove, arriving on a schedule.
     */
    #[Test]
    public function a_list_is_not_a_habit(): void
    {
        $student = $this->student();

        $this->expectException(RuntimeException::class);

        (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            '1. Read an article 2. Email someone 3. Update your resume',
            HabitCadence::Daily,
        );
    }

    #[Test]
    public function the_same_habit_is_not_prescribed_twice(): void
    {
        $student = $this->student();
        $habit = $this->habitFor($student);

        $this->expectException(RuntimeException::class);

        (new HabitTracker)->prescribe(
            $student,
            $this->gapFor($student),
            strtoupper($habit->title),
            HabitCadence::Daily,
        );
    }

    #[Test]
    public function habits_are_retired_never_deleted(): void
    {
        $habit = $this->habitFor($this->student());

        $habit->retire('It was the wrong size.');
        $this->assertSame(HabitStatus::Retired, $habit->fresh()->status);

        $this->expectException(RuntimeException::class);
        $habit->delete();
    }

    /**
     * ⚠️ THE RULE THIS FEATURE EXISTS AROUND. Two students keep the habit the
     * same number of times. One said nothing about the days they missed; the
     * other said why. The one who answered honestly must never come out
     * behind — the same reason naming an obstacle moves readiness *up*.
     */
    #[Test]
    public function explaining_a_miss_never_costs_the_student(): void
    {
        CarbonImmutable::setTestNow('2026-09-16');
        $start = CarbonImmutable::parse('2026-09-03');

        $silent = $this->habitFor($this->student(), started: $start);
        $honest = $this->habitFor($this->student(), started: $start);

        $tracker = new HabitTracker;

        foreach (range(0, 13) as $offset) {
            $day = $start->addDays($offset);
            $kept = $offset < 4;

            $tracker->checkIn($silent, $kept, null, $day);
            $tracker->checkIn($honest, $kept, $kept ? null : 'I had practice until nine.', $day);
        }

        $this->assertSame(HabitStanding::Stalled, $tracker->standing($silent->fresh())['standing']);
        $this->assertSame(HabitStanding::PartOfTheWeek, $tracker->standing($honest->fresh())['standing']);

        CarbonImmutable::setTestNow();
    }

    /**
     * Silence is not the same as explaining, and a habit nobody has answered
     * for at all is the loudest case. The plausible kindness here — "do not
     * judge a habit with no check-ins" — would mean an abandoned habit sits
     * at "just started" forever, and the coach would never learn the one
     * thing it needed to know: that this habit was the wrong size.
     */
    #[Test]
    public function saying_nothing_still_counts(): void
    {
        CarbonImmutable::setTestNow('2026-09-16');
        $start = CarbonImmutable::parse('2026-09-03');

        $habit = $this->habitFor($this->student(), started: $start);

        $this->assertSame(
            HabitStanding::Stalled,
            (new HabitTracker)->standing($habit)['standing'],
            'A habit nobody has answered for cannot be doing well.',
        );

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function a_habit_too_young_to_judge_is_not_judged(): void
    {
        CarbonImmutable::setTestNow('2026-09-16');

        $habit = $this->habitFor($this->student(), started: CarbonImmutable::parse('2026-09-15'));

        $this->assertSame(HabitStanding::Starting, (new HabitTracker)->standing($habit)['standing']);

        CarbonImmutable::setTestNow();
    }

    /**
     * A Saturday is not a missed school day. Counting it as one would stall
     * every weekday habit by arithmetic alone.
     */
    #[Test]
    public function a_weekday_habit_is_not_judged_on_weekends(): void
    {
        CarbonImmutable::setTestNow('2026-09-16');
        $start = CarbonImmutable::parse('2026-09-03');

        $habit = $this->habitFor($this->student(), HabitCadence::Weekdays, $start);
        $tracker = new HabitTracker;

        $cursor = $start;
        while ($cursor->lessThanOrEqualTo(CarbonImmutable::parse('2026-09-16'))) {
            if (! $cursor->isWeekend()) {
                $tracker->checkIn($habit, true, null, $cursor);
            }
            $cursor = $cursor->addDay();
        }

        $standing = $tracker->standing($habit->fresh());

        // Ten school days in that fortnight, not fourteen days. Asserted on
        // the denominator rather than only on the resulting word, because
        // counting the four weekend days still lands above the threshold —
        // the standing alone cannot tell the two arithmetics apart.
        $this->assertSame(10, $standing['expected']);
        $this->assertSame(10, $standing['kept']);
        $this->assertSame(HabitStanding::PartOfTheWeek, $standing['standing']);

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function answering_twice_in_a_day_does_not_answer_twice(): void
    {
        $habit = $this->habitFor($this->student());
        $tracker = new HabitTracker;

        $tracker->checkIn($habit, true);
        $tracker->checkIn($habit, true);
        $tracker->checkIn($habit, false);

        $this->assertSame(1, $habit->checkIns()->count());
        $this->assertTrue($habit->checkIns()->first()->happened);
    }

    /**
     * A note arriving after the fact is the student saying more about a day
     * they already answered for. Rewriting the answer itself is not.
     */
    #[Test]
    public function a_day_can_gain_a_note_but_not_a_different_answer(): void
    {
        $habit = $this->habitFor($this->student());
        $tracker = new HabitTracker;

        $checkIn = $tracker->checkIn($habit, false);
        $tracker->checkIn($habit, false, 'I forgot until it was too late.');

        $this->assertSame('I forgot until it was too late.', $checkIn->fresh()->note);

        $this->expectException(RuntimeException::class);
        $checkIn->fresh()->update(['happened' => true]);
    }

    /**
     * ⚠️ Nothing numeric reaches the student. The counts exist so the coach
     * can tell a habit is the wrong size and so these tests can pin it — not
     * so a sixteen-year-old is handed a score for their week.
     */
    #[Test]
    public function the_student_is_shown_words_and_never_numbers(): void
    {
        $habit = $this->habitFor($this->student());
        $tracker = new HabitTracker;
        $tracker->checkIn($habit, true);

        $shown = $tracker->forStudent($habit->user);

        $this->assertCount(1, $shown);

        foreach (['expected', 'kept', 'explained', 'streak', 'percent', 'score'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $shown[0], "The student is being shown '{$forbidden}'.");
        }

        foreach ($shown[0] as $key => $value) {
            $this->assertIsNotInt($value, "The student is being shown a number under '{$key}'.");
        }
    }

    /**
     * A stalled habit gets a smaller habit, never "try harder". A tool that
     * tells a teenager to recommit has started doing the thing it exists to
     * stop.
     */
    #[Test]
    public function a_stalled_habit_is_made_smaller_not_insisted_upon(): void
    {
        $move = HabitStanding::Stalled->nextMove();

        $this->assertStringContainsString('smaller', $move);

        foreach (['try harder', 'commit', 'discipline', 'no excuses'] as $scold) {
            $this->assertStringNotContainsStringIgnoringCase($scold, $move);
        }

        $this->assertStringNotContainsStringIgnoringCase('fail', HabitStanding::Stalled->meaning());
    }
}
