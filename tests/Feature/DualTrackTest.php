<?php

namespace Tests\Feature;

use App\Ai\Agents\VocationalAnalysis;
use App\Ai\Tools\GetPathwayProfileTool;
use App\Enums\ConfidenceLevel;
use App\Enums\EvidenceStanding;
use App\Enums\SignalTrack;
use App\Enums\SignalType;
use App\Models\Assessment;
use App\Models\SignalExtraction;
use App\Models\User;
use App\Support\DualTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 0.8 — blueprint 10.3's dual track.
 *
 * The invariant under test is not "the weights are right." It is that the
 * model supplies only attribution and the code supplies every judgement, so
 * no phrasing in a model's output can promote a pathway the student has only
 * ever wanted into one they have demonstrated.
 */
class DualTrackTest extends TestCase
{
    use RefreshDatabase;

    protected Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, SignalExtraction>
     */
    protected function signals(array ...$specs): Collection
    {
        foreach ($specs as $position => $spec) {
            SignalExtraction::create([
                'assessment_id' => $this->assessment->id,
                'type' => $spec[0],
                'track' => $spec[1],
                'content' => 'a signal',
                'verbatim' => 'a signal',
                'sort_order' => $position,
            ]);
        }

        return $this->assessment->signalExtractions()->get();
    }

    protected function scored(array $evidence, int $score = 90): array
    {
        return [['category' => 'Health & Medicine', 'score' => $score, 'evidence' => $evidence]];
    }

    #[Test]
    public function references_are_stable_between_rendering_and_resolving(): void
    {
        $signals = $this->signals(
            [SignalType::Desire, SignalTrack::Aspiration],
            [SignalType::Skill, SignalTrack::Demonstrated],
        );

        $index = DualTrack::index($signals);

        $this->assertSame(['S1', 'S2'], array_keys($index));
        $this->assertSame(SignalType::Desire, $index['S1']->type);

        // Resolving a second time, from a differently ordered collection, must
        // produce the same mapping — otherwise a reference silently means a
        // different signal on replay and nothing downstream looks wrong.
        $this->assertSame(
            array_map(fn ($signal) => $signal->id, $index),
            array_map(fn ($signal) => $signal->id, DualTrack::index($signals->reverse()->values())),
        );
    }

    /**
     * The whole point of the split. Wanting to be a paramedic and having run
     * the first-aid tent for two summers must not produce the same result.
     */
    #[Test]
    public function wanting_something_is_never_demonstrated_evidence(): void
    {
        $signals = $this->signals(
            [SignalType::Desire, SignalTrack::Aspiration],
            [SignalType::Interest, SignalTrack::Aspiration],
            [SignalType::Value, SignalTrack::Aspiration],
        );

        $annotated = DualTrack::annotate($this->scored(['S1', 'S2', 'S3']), $signals);

        $this->assertSame(0.0, $annotated[0]['demonstrated_weight']);
        $this->assertSame(EvidenceStanding::AspirationOnly->value, $annotated[0]['evidence_standing']);
    }

    #[Test]
    public function having_done_it_is_demonstrated_evidence(): void
    {
        $signals = $this->signals(
            [SignalType::Skill, SignalTrack::Demonstrated],
            [SignalType::Burden, SignalTrack::Demonstrated],
        );

        $annotated = DualTrack::annotate($this->scored(['S1', 'S2']), $signals);

        $this->assertSame(2.0, $annotated[0]['demonstrated_weight']);
        $this->assertSame(EvidenceStanding::Demonstrated->value, $annotated[0]['evidence_standing']);
    }

    /**
     * One instance of anything is an anecdote. A single summer job must not,
     * by itself, tell a sixteen-year-old who they are.
     */
    #[Test]
    public function one_demonstrated_signal_is_emerging_not_demonstrated(): void
    {
        $signals = $this->signals([SignalType::Skill, SignalTrack::Demonstrated]);

        $annotated = DualTrack::annotate($this->scored(['S1']), $signals);

        $this->assertSame(EvidenceStanding::Emerging->value, $annotated[0]['evidence_standing']);
    }

    /**
     * A desire filed on the demonstrated track is the exact failure the split
     * exists to catch, and the model is the thing most likely to cause it.
     */
    #[Test]
    public function a_desire_mislabelled_as_demonstrated_still_does_not_count(): void
    {
        $signals = $this->signals(
            [SignalType::Desire, SignalTrack::Demonstrated],
            [SignalType::Interest, SignalTrack::Demonstrated],
        );

        $annotated = DualTrack::annotate($this->scored(['S1', 'S2']), $signals);

        $this->assertSame(0.0, $annotated[0]['demonstrated_weight']);
        $this->assertSame(EvidenceStanding::AspirationOnly->value, $annotated[0]['evidence_standing']);
    }

    /**
     * The same deterministic check as verbatim provenance: a citation to
     * something that does not exist is dropped, not trusted.
     */
    #[Test]
    public function an_invented_citation_is_discarded(): void
    {
        $signals = $this->signals([SignalType::Skill, SignalTrack::Demonstrated]);

        $annotated = DualTrack::annotate($this->scored(['S1', 'S99', 'S400', 'nonsense']), $signals);

        $this->assertSame(['S1'], $annotated[0]['evidence']);
        $this->assertSame(1.0, $annotated[0]['demonstrated_weight']);
    }

    #[Test]
    public function a_category_that_cites_only_inventions_is_unevidenced(): void
    {
        $signals = $this->signals([SignalType::Skill, SignalTrack::Demonstrated]);

        $annotated = DualTrack::annotate($this->scored(['S42', 'S43']), $signals);

        $this->assertSame([], $annotated[0]['evidence']);
        $this->assertSame(EvidenceStanding::Unevidenced->value, $annotated[0]['evidence_standing']);
    }

    #[Test]
    public function citing_the_same_signal_twice_does_not_double_its_weight(): void
    {
        $signals = $this->signals([SignalType::Skill, SignalTrack::Demonstrated]);

        $annotated = DualTrack::annotate($this->scored(['S1', 'S1', 'S1']), $signals);

        $this->assertSame(1.0, $annotated[0]['demonstrated_weight']);
        $this->assertSame(EvidenceStanding::Emerging->value, $annotated[0]['evidence_standing']);
    }

    /**
     * An assessment that predates Layer 4, or whose signal pass failed, must
     * still produce a profile. Unevidenced is true, and true is enough.
     */
    #[Test]
    public function an_assessment_with_no_signals_still_annotates(): void
    {
        $annotated = DualTrack::annotate($this->scored([]), collect());

        $this->assertSame(EvidenceStanding::Unevidenced->value, $annotated[0]['evidence_standing']);
    }

    #[Test]
    public function a_category_the_model_gave_no_evidence_key_at_all_still_annotates(): void
    {
        $annotated = DualTrack::annotate(
            [['category' => 'Health & Medicine', 'score' => 90]],
            $this->signals([SignalType::Skill, SignalTrack::Demonstrated]),
        );

        $this->assertSame(EvidenceStanding::Unevidenced->value, $annotated[0]['evidence_standing']);
    }

    /**
     * The gap belongs to the leading pathway only. A student handed four
     * distances has been handed a report; one is something to do.
     */
    #[Test]
    public function only_the_leading_pathway_gets_a_next_move(): void
    {
        $signals = $this->signals(
            [SignalType::Desire, SignalTrack::Aspiration],
            [SignalType::Skill, SignalTrack::Demonstrated],
        );

        $annotated = DualTrack::annotate([
            ['category' => 'Trades & Construction', 'score' => 40, 'evidence' => ['S2']],
            ['category' => 'Health & Medicine', 'score' => 91, 'evidence' => ['S1']],
        ], $signals);

        $gap = DualTrack::gap($annotated);

        $this->assertSame('Health & Medicine', $gap['category']);
        $this->assertSame(EvidenceStanding::AspirationOnly->value, $gap['standing']);
        $this->assertFalse($gap['supports_naming_a_direction']);
        $this->assertNotEmpty($gap['next_move']);
    }

    #[Test]
    public function there_is_no_gap_without_a_pathway(): void
    {
        $this->assertNull(DualTrack::gap([]));
    }

    /**
     * Product invariant: nothing numeric ever reaches the student, and the
     * gap is the part written to be quoted to them directly.
     */
    #[Test]
    public function nothing_the_student_is_shown_is_a_number(): void
    {
        foreach (EvidenceStanding::cases() as $standing) {
            $this->assertDoesNotMatchRegularExpression('/\d|%/', $standing->meaning());
            $this->assertDoesNotMatchRegularExpression('/\d|%/', $standing->nextMove());
            $this->assertDoesNotMatchRegularExpression('/\d|%/', $standing->label());
        }
    }

    /**
     * Blueprint 10.3 is explicit that the distance is "not automatic
     * validation, and not dismissal." Language that reads as a deficiency
     * would make the split harmful rather than useful.
     */
    #[Test]
    public function aspiration_is_never_described_as_a_deficiency(): void
    {
        $text = strtolower(
            EvidenceStanding::AspirationOnly->meaning().' '.EvidenceStanding::AspirationOnly->nextMove()
        );

        foreach (['lack', 'fail', 'weak', 'deficien', 'unrealistic', 'just a', 'only a', 'behind'] as $dismissal) {
            $this->assertStringNotContainsString($dismissal, $text);
        }
    }

    /**
     * Wanting alone is never grounds to tell a student what they are. That is
     * identity foreclosure with extra steps.
     */
    #[Test]
    public function aspiration_alone_never_supports_naming_a_direction(): void
    {
        $this->assertFalse(EvidenceStanding::AspirationOnly->supportsNamingADirection());
        $this->assertFalse(EvidenceStanding::Unevidenced->supportsNamingADirection());
        $this->assertTrue(EvidenceStanding::Emerging->supportsNamingADirection());
        $this->assertTrue(EvidenceStanding::Demonstrated->supportsNamingADirection());
    }

    /**
     * The prompt and the resolver must agree about what S4 means. If the
     * agent stops printing references, every citation becomes an invention.
     */
    #[Test]
    public function the_prompt_shows_the_references_it_asks_to_be_cited(): void
    {
        $this->signals(
            [SignalType::Desire, SignalTrack::Aspiration],
            [SignalType::Skill, SignalTrack::Demonstrated],
        );

        $prompt = (new VocationalAnalysis($this->assessment->fresh()))->buildPrompt();

        $this->assertStringContainsString('S1', $prompt);
        $this->assertStringContainsString('S2', $prompt);
        $this->assertStringContainsString('discarded', $prompt);
    }

    /**
     * The gap is only worth computing if it survives to the coach, which is
     * the thing that turns it into an experiment the student actually runs.
     */
    #[Test]
    public function the_gap_reaches_the_coach(): void
    {
        $student = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $gap = [
            'category' => 'Health & Medicine',
            'standing' => EvidenceStanding::AspirationOnly->value,
            'meaning' => EvidenceStanding::AspirationOnly->meaning(),
            'next_move' => EvidenceStanding::AspirationOnly->nextMove(),
            'supports_naming_a_direction' => false,
        ];

        $assessment->vocationalProfile()->create([
            'confidence_level' => ConfidenceLevel::Strong,
            'evidence_gap' => $gap,
        ]);

        $payload = json_decode(
            (new GetPathwayProfileTool($student))->handle(
                new Request([]),
            ),
            associative: true,
        );

        $this->assertSame($gap, $payload['evidence_gap']);

        // Confidence is strong and the gap still says no. Confidence measures
        // how clearly they answered, not whether they have lived any of it —
        // the two must not be able to substitute for each other.
        $this->assertTrue($payload['may_name_a_direction']);
        $this->assertFalse($payload['evidence_gap']['supports_naming_a_direction']);
    }

    /**
     * Observed in the wild on 2026-09-16, the first time the corpus was driven
     * through a locally hosted model: `llama3.2:3b` filled every `evidence`
     * array with **our own question text** rather than signal references —
     * "Com o que as pessoas consistentemente buscam minha ajuda?" cited as
     * though the student had said it.
     *
     * Nothing needed fixing; this pins why. Evidence is a reference into the
     * signal index, so a citation that is not one cannot resolve, and the row
     * degrades to unevidenced. The failure a weaker model is most likely to
     * produce is the one the design already refuses to act on — which is the
     * property that makes a local model safe to point at this engine at all.
     */
    #[Test]
    public function a_model_citing_our_own_questions_back_earns_no_evidence(): void
    {
        $signals = $this->signals(
            [SignalType::Skill, SignalTrack::Demonstrated],
            [SignalType::Burden, SignalTrack::Demonstrated],
        );

        $annotated = DualTrack::annotate([[
            'category' => 'Healing & Care',
            'score' => 70,
            'evidence' => [
                'Com o que as pessoas consistentemente buscam minha ajuda?',
                'Quando eu me sinto mais eficaz e em meu elemento?',
            ],
        ]], $signals);

        $this->assertSame([], $annotated[0]['evidence']);
        $this->assertSame(0.0, $annotated[0]['demonstrated_weight']);
        $this->assertSame(
            EvidenceStanding::Unevidenced->value,
            $annotated[0]['evidence_standing'],
            'Text the model invented was counted as though the student had said it.',
        );

        /*
         | Dropped is correct. Dropped *silently* is how a seven-minute run
         | comes back with every layer working and nothing evidenced, and no
         | way to tell which layer gave up. The drop is now reportable.
         */
        $this->assertSame(
            [
                'Com o que as pessoas consistentemente buscam minha ajuda?',
                'Quando eu me sinto mais eficaz e em meu elemento?',
            ],
            DualTrack::uncitedReferences([[
                'category' => 'Healing & Care',
                'score' => 70,
                'evidence' => [
                    'Com o que as pessoas consistentemente buscam minha ajuda?',
                    'Quando eu me sinto mais eficaz e em meu elemento?',
                ],
            ]], $signals),
        );
    }

    /**
     * A model that cited correctly has nothing to report, or the warning fires
     * on every healthy run and stops meaning anything.
     */
    #[Test]
    public function a_model_that_cites_real_signals_reports_nothing_discarded(): void
    {
        $signals = $this->signals(
            [SignalType::Skill, SignalTrack::Demonstrated],
            [SignalType::Burden, SignalTrack::Demonstrated],
        );

        $this->assertSame([], DualTrack::uncitedReferences([[
            'category' => 'Healing & Care',
            'score' => 70,
            'evidence' => ['S1', 'S2'],
        ]], $signals));

        $this->assertSame(['S9'], DualTrack::uncitedReferences([[
            'category' => 'Healing & Care',
            'score' => 70,
            'evidence' => ['S1', 'S9'],
        ]], $signals));
    }
}
