<?php

namespace Tests\Feature;

use App\Enums\BrainEntrySource;
use App\Models\BrainEntry;
use App\Models\User;
use App\Support\ThresholdSurfacing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 2.5 — interrupting when a pattern repeats enough to name.
 *
 * The invariant under everything here: the system surfaces what the student
 * already said and does not write what they would have said. So the "name" of
 * a pattern is the student's own repeated word, and the payload is their own
 * sentences.
 */
class ThresholdSurfacingTest extends TestCase
{
    use RefreshDatabase;

    protected function said(User $user, string $content, string $on): BrainEntry
    {
        return BrainEntry::create([
            'user_id' => $user->id,
            'content' => $content,
            'source' => BrainEntrySource::Coach,
            'occurred_at' => CarbonImmutable::parse($on),
        ]);
    }

    protected function studentWhoKeepsSayingIt(): User
    {
        $user = User::factory()->create();

        $this->said($user, 'I liked rewiring the lamp in the garage more than I expected.', '2026-03-02');
        $this->said($user, 'Spent Saturday rewiring an old amp with my uncle.', '2026-03-28');
        $this->said($user, 'The rewiring stuff is the only homework I actually finish.', '2026-04-20');
        $this->said($user, 'Asked about rewiring the stage lights for the spring play.', '2026-05-11');

        return $user;
    }

    /**
     * ⚠️ THE INVARIANT. The pattern is their word and their sentences. If the
     * system ever supplies the noun, it has written the student's conclusion
     * for them.
     */
    #[Test]
    public function the_pattern_is_named_in_the_students_own_word(): void
    {
        $user = $this->studentWhoKeepsSayingIt();

        $pattern = (new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-05-20'));

        $this->assertNotNull($pattern);
        $this->assertSame('rewiring', $pattern['term']);
        $this->assertSame(4, $pattern['entry_count']);
        $this->assertCount(4, $pattern['in_their_words']);

        foreach ($pattern['in_their_words'] as $said) {
            $this->assertStringContainsString(
                $said['content'],
                $user->brainEntries->pluck('content')->implode("\n"),
                'A sentence was handed back that the student never said.',
            );
        }

        // Nowhere for a generated label to live.
        foreach (['label', 'summary', 'interpretation', 'meaning'] as $ours) {
            $this->assertArrayNotHasKey($ours, $pattern);
        }
    }

    /**
     * Repetition inside one conversation is enthusiasm, not a pattern.
     * Treating it as one teaches a student the system mistakes volume for
     * meaning.
     */
    #[Test]
    public function saying_it_four_times_in_one_afternoon_is_not_a_pattern(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $i) {
            $this->said($user, "Honestly the rewiring part is the best bit, number {$i}.", '2026-03-02');
        }

        $this->assertNull((new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-03-03')));
    }

    #[Test]
    public function three_mentions_is_not_yet_enough(): void
    {
        $user = User::factory()->create();

        $this->said($user, 'Rewiring the lamp was good.', '2026-03-02');
        $this->said($user, 'More rewiring on Saturday.', '2026-03-28');
        $this->said($user, 'Rewiring again, honestly.', '2026-04-20');
        $this->said($user, 'Nothing much happened this week at all.', '2026-05-01');

        $this->assertNull((new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-05-02')));
    }

    /**
     * Said once, "you keep coming back to this" is the product working. Said
     * on every visit it is nagging, and a tool that nags a teenager about
     * their own words gets closed.
     */
    #[Test]
    public function the_same_pattern_is_not_raised_twice(): void
    {
        $user = $this->studentWhoKeepsSayingIt();
        $surfacing = new ThresholdSurfacing;
        $asOf = CarbonImmutable::parse('2026-05-20');

        $pattern = $surfacing->detect($user, $asOf);
        $surfacing->record($user, $pattern, $asOf);

        $this->assertNull($surfacing->detect($user, $asOf->addDays(7)));
    }

    /**
     * But it is not silenced forever — a thing still true nine months later
     * is worth raising again.
     */
    #[Test]
    public function after_long_enough_it_can_be_raised_again(): void
    {
        $user = $this->studentWhoKeepsSayingIt();
        $surfacing = new ThresholdSurfacing;
        $asOf = CarbonImmutable::parse('2026-05-20');

        $surfacing->record($user, $surfacing->detect($user, $asOf), $asOf);

        $this->assertNotNull($surfacing->detect($user, $asOf->addDays(ThresholdSurfacing::COOLDOWN_DAYS + 1)));
    }

    /**
     * ⚠️ HARD INVARIANT. A student who has said "hopeless" across two months
     * has told us something, and it is not that they are drawn to a pathway.
     * Surfacing that as a vocational pattern is the product performing
     * interpretation on distress.
     */
    #[Test]
    public function repeated_distress_is_flagged_for_a_person_not_read_as_a_calling(): void
    {
        $user = User::factory()->create();

        $this->said($user, 'It all feels pretty hopeless lately.', '2026-03-02');
        $this->said($user, 'Still hopeless about the whole thing.', '2026-03-28');
        $this->said($user, 'I said hopeless again to my friend.', '2026-04-20');
        $this->said($user, 'Hopeless is the word that keeps coming out.', '2026-05-11');

        $pattern = (new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-05-20'));

        $this->assertNotNull($pattern);
        $this->assertSame('hopeless', $pattern['term']);
        $this->assertTrue($pattern['needs_human'], 'Distress was about to be surfaced as a vocational pattern.');
    }

    /**
     * Ordinary words repeat constantly. If they counted, every student would
     * be told they keep coming back to "something".
     */
    #[Test]
    public function ordinary_words_are_not_patterns(): void
    {
        $user = User::factory()->create();

        $this->said($user, 'I think I want to do something, I really think so.', '2026-03-02');
        $this->said($user, 'I think about it, I want something different.', '2026-03-28');
        $this->said($user, 'I really think I want something better.', '2026-04-20');
        $this->said($user, 'I think that is what I want, something like that.', '2026-05-11');

        $this->assertNull((new ThresholdSurfacing)->detect($user, CarbonImmutable::parse('2026-05-20')));
    }
}
