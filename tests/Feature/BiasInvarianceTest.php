<?php

namespace Tests\Feature;

use App\Enums\ConfidenceLevel;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\SignalExtraction;
use App\Support\ConfidenceCalculator;
use App\Support\ResponseQuality;
use App\Support\ValidationReadout;
use App\Support\WordCount;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 5.4 — bias testing, done on the thing we can actually test.
 *
 * The blueprint asks for bias testing across culture, gender, socioeconomic
 * background, neurodiversity and educational access, "evaluating meaning and
 * motivation, never polish or eloquence". We deliberately do not hold most of
 * those attributes and will not infer them (see
 * {@see ValidationReadout}), which rules out the usual approach
 * of slicing outcomes by demographic group.
 *
 * What we can do is stronger anyway: hold the *meaning* fixed and vary the
 * *expression*, then assert the engine does not move. Polish, spelling,
 * register and script are the observable proxies for schooling, class and
 * culture, and if the engine is blind to them it cannot be discriminating on
 * them through that route.
 *
 * This pass found a real defect on its first run: every word count in the
 * product used `str_word_count()`, which cannot count a script that does not
 * put spaces between words. Depending on the machine's locale it reads such an
 * answer as zero words — a student writing fluent Chinese permanently capped
 * at "not enough evidence", indistinguishable from one who typed nothing — or
 * as one word per UTF-8 byte, which is three times too generous. Locale-
 * dependent unfairness is the worst kind: it does not reproduce on the laptop
 * where the test was written. See {@see WordCount}.
 */
class BiasInvarianceTest extends TestCase
{
    /**
     * @param  list<array{type: SignalType, track: SignalTrack}>  $shape
     * @return Collection<int, SignalExtraction>
     */
    protected function signals(array $shape): Collection
    {
        return collect($shape)->map(fn (array $signal) => new SignalExtraction([
            'type' => $signal['type'],
            'track' => $signal['track'],
            'content' => 'A signal.',
            'verbatim' => 'A span.',
        ]));
    }

    /**
     * The defect this pass was written to find. A student answering in Chinese
     * counted as having written nothing at all, and every downstream ceiling
     * read that as an empty assessment rather than as an engine that cannot
     * count.
     */
    #[Test]
    public function a_script_without_spaces_is_not_counted_as_silence(): void
    {
        $chinese = '我喜欢修理东西，因为看到坏掉的机器重新运转让我觉得很踏实，这是我一直在做的事情。';
        $japanese = 'わたしは物を直すのが好きです。壊れた機械が動き出すのを見ると、とても落ち着いた気持ちになります。';

        /*
         | Exact counts, not merely "more than nothing". `str_word_count()`
         | is locale-dependent: in the C locale it reads these as zero words,
         | and in a locale where high bytes count as letters it reads them as
         | one word per UTF-8 *byte* — three times the truth. A greater-than-
         | zero assertion passes under the second failure and would have let
         | the defect back in. One unit per character is the claim.
         */
        $this->assertSame(37, WordCount::of($chinese));
        $this->assertSame(45, WordCount::of($japanese));

        $this->assertNotSame(
            ConfidenceLevel::InsufficientEvidence,
            ConfidenceCalculator::evidenceCeiling(array_fill(0, 8, $chinese)),
            'A student writing fluently in Chinese must not be capped at "not enough evidence".',
        );

        /*
         | And the ceiling has to be the *same* as for an answer of the same
         | length in English, not merely non-empty. Counting bytes would make
         | the Chinese student look three times more forthcoming than the
         | English one, which is the same unfairness pointing the other way.
         */
        $english = implode(' ', array_fill(0, WordCount::of($chinese), 'again'));

        $this->assertSame(
            ConfidenceCalculator::evidenceCeiling(array_fill(0, 8, $english)),
            ConfidenceCalculator::evidenceCeiling(array_fill(0, 8, $chinese)),
            'The same amount of writing must buy the same ceiling in either script.',
        );
    }

    /**
     * And an accented language is not counted twice. The old counter split
     * "quedé" into two words, which inflated Spanish answers — unfairness in
     * the other direction, and equally invisible.
     */
    #[Test]
    public function an_accent_is_not_a_word_boundary(): void
    {
        $this->assertSame(1, WordCount::of('quedé'));
        $this->assertSame(1, WordCount::of('Straße'));
        $this->assertSame(1, WordCount::of('naïve'));
        $this->assertSame(4, WordCount::of('me quedé con ella'));
    }

    /**
     * Two answers that say the same thing, one written the way a student who
     * has been taught to write writes, the other the way most people type.
     * Same meaning, same signals, same length — so the same score.
     *
     * Any future heuristic that rewards capitalisation, spelling, punctuation
     * or vocabulary would break exactly here, which is the point.
     */
    #[Test]
    public function polish_does_not_change_what_an_answer_is_worth(): void
    {
        $polished = 'I stayed with my grandmother throughout the night after her surgery, and I explained '
            .'to her carefully what the nurses were going to do next so that she would not be frightened.';

        $plain = 'i stayed with my grandma all night after her surgery and i kept telling her what the '
            .'nurses was gonna do next so she wouldnt be scared of it happening to her';

        $signals = $this->signals([
            ['type' => SignalType::Skill, 'track' => SignalTrack::Demonstrated],
            ['type' => SignalType::Desire, 'track' => SignalTrack::Demonstrated],
        ]);

        $this->assertSame(
            ResponseQuality::bands($polished, $signals),
            ResponseQuality::bands($plain, $signals),
            'The same evidence, expressed less formally, was scored differently.',
        );
    }

    /**
     * The same invariance at the level the student actually feels: the
     * confidence ceiling on a whole assessment.
     */
    #[Test]
    public function the_evidence_ceiling_does_not_move_with_register(): void
    {
        $polished = array_fill(0, 8, 'I have always been the person my family asks when something needs '
            .'repairing, and I find that I enjoy the part where I work out why it failed.');

        /*
         | Deliberately matched on length and meaning and *unmatched* on
         | register: the same twenty-eight words, nine of them long in the
         | first and two in the second. A pair balanced on word length as well
         | would pass this test no matter what the engine rewarded.
         */
        $plain = array_fill(0, 8, 'im the one my mum and dad ask when a thing is broke and the bit i '
            .'like best is when i work out why it went bad');

        /*
         | Asserted on the measure as well as on the band. A ceiling is one of
         | five words, so two texts can be scored quite differently and still
         | land on the same one; a mutation that started rewarding long words
         | or long sentences would pass an equality check on the band alone.
         | The count is where the unfairness would first show up, so that is
         | where the assertion goes.
         */
        $this->assertSame(WordCount::of($polished[0]), WordCount::of($plain[0]));

        /*
         | And at every depth, not only at eight answers. The bands are coarse
         | in the middle and tight at the edges, and a student who writes three
         | plain answers is exactly the one an eloquence-sensitive engine would
         | fail first.
         */
        foreach ([1, 2, 3, 5, 8] as $depth) {
            $this->assertSame(
                ConfidenceCalculator::evidenceCeiling(array_slice($polished, 0, $depth)),
                ConfidenceCalculator::evidenceCeiling(array_slice($plain, 0, $depth)),
                "Register changed the ceiling at {$depth} answers.",
            );
        }

        /*
         | The same claim again, this time with the pair sitting on a band
         | boundary. Twenty words each; a hundred and forty-five characters
         | against eighty-three. Anything that measured writing by its length
         | in characters, or by average word length, would agree with the
         | assertions above and disagree here — which is where a student who
         | writes shortly and plainly actually lives.
         */
        $formal = array_fill(0, 8, 'I generally prefer to understand the underlying mechanism before '
            .'attempting any repair, particularly where somebody else depends upon the outcome');

        $blunt = array_fill(0, 8, 'i like to get why a thing works before i try to fix it up when its '
            .'for someone else');

        $this->assertSame(WordCount::of($formal[0]), WordCount::of($blunt[0]));

        $this->assertSame(
            ConfidenceCalculator::evidenceCeiling($formal),
            ConfidenceCalculator::evidenceCeiling($blunt),
        );
    }

    /**
     * What the score *does* respond to is evidence. An answer whose signals
     * are things the person has actually done outscores one whose signals are
     * things they want — regardless of how either is written.
     */
    #[Test]
    public function the_score_moves_on_evidence_rather_than_expression(): void
    {
        $text = array_fill(0, 30, 'word');
        $text = implode(' ', $text);

        $demonstrated = ResponseQuality::bands($text, $this->signals([
            ['type' => SignalType::Skill, 'track' => SignalTrack::Demonstrated],
            ['type' => SignalType::Skill, 'track' => SignalTrack::Demonstrated],
        ]));

        $aspiration = ResponseQuality::bands($text, $this->signals([
            ['type' => SignalType::Skill, 'track' => SignalTrack::Aspiration],
            ['type' => SignalType::Skill, 'track' => SignalTrack::Aspiration],
        ]));

        $this->assertGreaterThan($aspiration['total'], $demonstrated['total']);
        $this->assertSame($demonstrated['substance'], $aspiration['substance']);
    }
}
