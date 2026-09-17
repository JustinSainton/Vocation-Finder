<?php

namespace Tests\Feature;

use App\Support\ConversationLocale;
use App\Support\RedTeamLint;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Blueprint Layer 8. Most of §10.4 and §13 is a word list, so most of the
 * red-team pass needs no model at all.
 *
 * The severity split is the point: language that could foreclose a young
 * person's identity blocks the result entirely, while language that is merely
 * off-voice is logged and shipped. Withholding someone's result over the word
 * "leverage" would be its own kind of failure.
 */
class RedTeamLintTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blockingLanguage(): array
    {
        return [
            'claims divine instruction' => ['God has called you to nursing.', 'divine_presumption'],
            'speaks for the Lord' => ['The Lord is calling you into teaching.', 'divine_presumption'],
            'names the calling outright' => ['This is your calling.', 'divine_presumption'],
            'asserts destiny' => ['You were born for this work.', 'determinism'],
            // Found by the golden corpus. Telling a student what they *are*
            // forecloses harder than telling them what to do, and none of the
            // phrases above reached it.
            'tells them what they are' => ['This is who you are, and nothing will change it.', 'determinism'],
            'tells them what they are, at a distance' => ['That is who you are at bottom.', 'determinism'],
            'the natural form of without a doubt' => ['There is no doubt about it.', 'false_certainty'],
            'promises the future certainly' => ['You will certainly thrive in that work.', 'false_certainty'],
            'promises the future definitely' => ['You will definitely find that rewarding.', 'false_certainty'],
            'promises the future absolutely' => ['You will absolutely be good at this.', 'false_certainty'],
            'uses the word destiny' => ['Your destiny is in medicine.', 'determinism'],
            'forecloses the path' => ['This is the only path for you.', 'determinism'],
            'instructs a career' => ['You should become an architect.', 'determinism'],
            'promises success' => ['You will be successful if you choose this.', 'determinism'],
            'guarantees' => ['This is a guaranteed fit for your gifts.', 'false_certainty'],
            'claims a perfect fit' => ['Teaching is a perfect fit for you.', 'false_certainty'],
            'goes mystical' => ['The universe wants you in this field.', 'mysticism'],
            'diagnoses' => ['Your answers suggest an anxiety disorder.', 'clinical'],
            'uses diagnostic stems' => ['This is diagnostic of a caregiving pattern.', 'clinical'],
            'names a trauma response' => ['This reads as a trauma response.', 'clinical'],
            'diagnoses the reader outright' => ['You have been diagnosed with an attention disorder.', 'clinical'],
            'attributes a condition possessively' => ['Your symptoms point somewhere else entirely.', 'clinical'],
            'diagnoses in the second person' => ['We would diagnose you as anxious about this.', 'clinical'],
            'characterises the person as pathological' => ['This reads as pathological avoidance.', 'clinical'],
            'dead-ends the student' => ['There is not enough information to help you.', 'dead_end'],
            'reduces them to a percentage' => ['You are an 87% match for healthcare.', 'mechanical_score'],
            'reduces them to a score' => ['Your compatibility score is high.', 'mechanical_score'],
            'rates them out of ten' => ['We rate your fit 8 out of 10.', 'mechanical_score'],
        ];
    }

    #[DataProvider('blockingLanguage')]
    public function test_it_blocks_language_the_product_must_never_use(string $text, string $rule): void
    {
        $blocking = RedTeamLint::blocking(RedTeamLint::inspect($text));

        $this->assertNotEmpty($blocking, "Expected [{$text}] to be blocked.");
        $this->assertContains($rule, array_column($blocking, 'rule'));
        $this->assertFalse(RedTeamLint::passes($text));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function offVoiceLanguage(): array
    {
        return [
            'flatters' => ['You have an extraordinary gift for this.', 'flattery'],
            'hypes' => ['This could be your dream job.', 'hype'],
            'talks like a consultant' => ['You could leverage this strength.', 'corporate_speak'],
            'objectifies' => ['The respondent shows a caregiving pattern.', 'objectifying'],
        ];
    }

    /**
     * Off-voice is a defect, not a danger. It is recorded and the student
     * still gets their result.
     */
    #[DataProvider('offVoiceLanguage')]
    public function test_it_warns_without_blocking_on_off_voice_language(string $text, string $rule): void
    {
        $findings = RedTeamLint::inspect($text);

        $this->assertSame([], RedTeamLint::blocking($findings));
        $this->assertContains($rule, array_column(RedTeamLint::warnings($findings), 'rule'));
        $this->assertTrue(RedTeamLint::passes($text));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function approvedLanguage(): array
    {
        return [
            'suggests' => ['Your story suggests a pull toward caring for people directly.'],
            'names a pattern' => ['A recurring pattern in your answers is a burden for people who are overlooked.'],
            'defers the decision' => ['Your next step is not to make a permanent decision, but to test this.'],
            'invites exploration' => ['This direction deserves further exploration.'],
            'admits uncertainty well' => ['There is not enough evidence yet to make a strong interpretation, but there is enough to know what we need to test next.'],
            'observes energy' => ['You appear most energized when you are explaining something to someone.'],
            'names evidence' => ['There is meaningful evidence that you move toward people in distress.'],
        ];
    }

    #[DataProvider('approvedLanguage')]
    public function test_it_passes_the_blueprints_own_approved_phrasings(string $text): void
    {
        $this->assertSame([], RedTeamLint::inspect($text), "Approved phrasing was flagged: {$text}");
    }

    /**
     * The §10.5 distinction is one clause wide and the lint must respect it:
     * "not enough information to help you" is forbidden, while "not enough
     * evidence yet ... to know what we need to test next" is mandated.
     */
    public function test_it_separates_a_dead_end_from_an_honest_admission_of_uncertainty(): void
    {
        $this->assertFalse(RedTeamLint::passes('There is not enough information to help you.'));
        $this->assertTrue(RedTeamLint::passes(
            'There is not enough evidence yet to make a strong interpretation, but there is enough to know what we need to test next.'
        ));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function innocentLookalikes(): array
    {
        return [
            'fateful is not fate' => ['That was a fateful summer for her.'],
            'heroic contains roi' => ['She described a heroic effort by her coach.'],
            'subject matter is not the subject' => ['The subject matter she loves most is biology.'],
            'a bare number is not a score' => ['You named three next steps worth testing.'],
            'an age is not a score' => ['At 17, this is a reasonable thing to be unsure about.'],
            'ordering is not a rating' => ['Consider talking to 2 people who do this work.'],
            'diagnostic work is a pathway not a diagnosis' => ['You lit up at the part where nobody knew what was wrong yet, which is what diagnostic work actually is.'],
            'diagnostics names a field' => ['Radiology and diagnostics both reward someone who notices what is off.'],
            'a diagnostician is a job' => ['A diagnostician spends their day on exactly that question.'],
            'pathology is a department' => ['A pathology lab is worth a visit before you decide.'],
            'serving people who have a disorder is a vocation' => ['You could work with children who have a speech disorder.'],
            'an interest in disorders is not a disorder' => ['Your answers suggest an interest in speech disorders.'],
            'doubt named honestly is the voice we want' => ['There is real doubt here, and that is worth saying plainly.'],
            'a past certainty is not a promise' => ['You certainly seemed to lose track of time doing it.'],
            'asking who they are is not telling them' => ['Who you are is still being worked out, and that is fine at sixteen.'],
            'a conditional future is not a guarantee' => ['You will probably find out quickly whether it holds.'],
        ];
    }

    /**
     * A blanket substring search would fire on every one of these. The lint
     * is worthless if it cries wolf, because the first response to a noisy
     * blocking check is to switch it off.
     */
    #[DataProvider('innocentLookalikes')]
    public function test_it_does_not_fire_on_innocent_lookalikes(string $text): void
    {
        $this->assertSame([], RedTeamLint::inspect($text), "False positive on: {$text}");
    }

    public function test_the_repair_instruction_names_the_offending_phrases(): void
    {
        $findings = RedTeamLint::inspect('God has called you to nursing, and it is a perfect fit.');

        $instruction = RedTeamLint::repairInstruction($findings);

        $this->assertStringContainsString('god has called you', $instruction);
        $this->assertStringContainsString('perfect fit', $instruction);
    }

    /**
     * A repair instruction is handed back to a model, so it must not itself
     * contain the language it is asking to have removed in a form that could
     * be copied through.
     */
    public function test_the_repair_instruction_states_the_rule_positively(): void
    {
        $instruction = RedTeamLint::repairInstruction(RedTeamLint::inspect('You were born for this.'));

        $this->assertStringContainsString('Say what the evidence suggests and what would', $instruction);
        $this->assertStringContainsString('never reduce this person to a', $instruction);
    }

    public function test_it_reports_every_distinct_violation_not_just_the_first(): void
    {
        $findings = RedTeamLint::inspect(
            'God has called you to this destiny, a guaranteed 95% match, and you should become a doctor.'
        );

        $rules = array_unique(array_column($findings, 'rule'));

        $this->assertContains('divine_presumption', $rules);
        $this->assertContains('determinism', $rules);
        $this->assertContains('false_certainty', $rules);
        $this->assertContains('mechanical_score', $rules);
    }

    public function test_it_is_case_insensitive(): void
    {
        $this->assertFalse(RedTeamLint::passes('GOD HAS CALLED YOU TO THIS.'));
        $this->assertFalse(RedTeamLint::passes('Your DESTINY is clear.'));
    }

    public function test_clean_text_produces_no_findings_at_all(): void
    {
        $narrative = <<<'TEXT'
        ## Opening Synthesis

        You wrote about sitting with someone who was crying until she stopped.
        That is not a small thing, and it recurs in your answers.

        ## Next Steps

        Ask someone who does this work what their hardest week looks like.
        TEXT;

        $this->assertSame([], RedTeamLint::inspect($narrative));
    }

    /**
     * ⚠️ A false clean is strictly worse than no lint, so a language with no
     * rules behind it is refused rather than passed. The covered set is the
     * three the product speaks, which is not a coincidence: a locale we will
     * generate in and cannot red-team in is a locale we should not generate
     * in.
     */
    public function test_a_narrative_it_cannot_read_is_not_called_clean(): void
    {
        $this->assertTrue(RedTeamLint::covers(null), 'No locale means the application default, which is English.');
        $this->assertTrue(RedTeamLint::covers('en'));
        $this->assertTrue(RedTeamLint::covers('en-US'));
        $this->assertTrue(RedTeamLint::covers('en_GB'));
        $this->assertTrue(RedTeamLint::covers('es'));
        $this->assertTrue(RedTeamLint::covers('es-419'));
        $this->assertTrue(RedTeamLint::covers('pt-BR'));

        $this->assertFalse(RedTeamLint::covers('ko'));
        $this->assertFalse(RedTeamLint::covers('fr'));

        $this->assertStringContainsString('ko', RedTeamLint::unverifiableLanguageReason('ko'));
    }

    /**
     * Every locale the product will actually speak in has rules behind it.
     * The two lists are allowed to diverge in only one direction: the lint may
     * read a language the product does not speak, never the reverse.
     */
    public function test_the_lint_reads_every_language_the_product_speaks(): void
    {
        foreach (ConversationLocale::supported() as $locale) {
            $this->assertTrue(
                RedTeamLint::covers($locale),
                "The product speaks {$locale} and the lint cannot read it.",
            );
        }
    }

    /**
     * The hole this used to guard is now closed by rules rather than by a
     * refusal. The same claim, in all three languages, is the same failure.
     */
    public function test_the_claim_is_caught_in_every_language_it_can_be_made_in(): void
    {
        $claims = [
            'God has called you to be a nurse.',
            'Dios te ha llamado a ser enfermera.',
            'Deus te chamou para ser enfermeira.',
        ];

        /*
         | Asserted on the rule rather than on the refusal. Every one of these
         | sentences would also trip something else if written out in full, so
         | a bare assertFalse would pass with the divine rule deleted — the
         | thing this test exists to hold.
         */
        foreach ($claims as $claim) {
            $rules = array_column(RedTeamLint::blocking(RedTeamLint::inspect($claim)), 'rule');

            $this->assertContains('divine_presumption', $rules, "Not caught: {$claim}");
        }
    }

    /**
     * A draft that switches language mid-paragraph is exactly where a banned
     * claim hides, so every language's rules run against every narrative
     * whatever the locale says.
     */
    public function test_a_claim_in_another_language_is_still_caught(): void
    {
        $this->assertFalse(RedTeamLint::passes(
            'You have real evidence of caring for people. Este es tu llamado.',
        ));
    }

    /**
     * Accents are folded before matching, because a model drops a tilde far
     * more readily than it drops a claim.
     */
    public function test_a_missing_accent_does_not_buy_a_pass(): void
    {
        $this->assertFalse(RedTeamLint::passes('Nao ha duvida de que voce nasceu para isso.'));
        $this->assertFalse(RedTeamLint::passes('Não há dúvida de que você nasceu para isso.'));
    }

    /**
     * And the finding quotes the span **as the model wrote it**, accents
     * intact. A repair instruction naming a string that appears nowhere in the
     * draft is an instruction that cannot be acted on — which is how the
     * clinical rule failed on the first live run.
     */
    public function test_the_finding_quotes_what_the_draft_actually_says(): void
    {
        $findings = RedTeamLint::inspect('Não há dúvida sobre isso.');

        $this->assertNotSame([], $findings);
        $this->assertSame('não há dúvida', $findings[0]['match']);
    }

    /**
     * Ordinary Spanish and Portuguese must still read clean. A lint that
     * refuses every narrative in a language has not gained that language, it
     * has only moved the refusal.
     */
    /**
     * Translating a banlist phrase by phrase carries the source language's
     * idiom across with it. English forecloses with *who you are*; Spanish
     * does it with *lo que eres*, and the first Spanish list only held the
     * literal translation of the English.
     */
    public function test_the_natural_spelling_of_the_claim_is_the_one_that_is_caught(): void
    {
        foreach ([
            'Esto es lo que eres y nada lo va a cambiar.',
            'Eso es quien eres.',
            'Isso é o que você é, e nada vai mudar isso.',
        ] as $claim) {
            $rules = array_column(RedTeamLint::blocking(RedTeamLint::inspect($claim)), 'rule');

            $this->assertContains('determinism', $rules, "Not caught: {$claim}");
        }
    }

    public function test_an_ordinary_narrative_in_spanish_or_portuguese_passes(): void
    {
        $spanish = <<<'TEXT'
        Lo que dijiste sobre cuidar a tu abuela aparece tres veces, y cada vez
        describes lo que hiciste, no lo que sentiste. Eso es evidencia, no una
        aspiración. Vale la pena probarlo antes de decidir nada.

        Habla con alguien que haga este trabajo y pregúntale cómo es su semana
        más difícil.
        TEXT;

        $portuguese = <<<'TEXT'
        O que você escreveu sobre consertar coisas com o seu pai aparece em
        três respostas diferentes. Você descreve o que fez, não o que gostaria
        de ter feito, e essa é a diferença que importa aqui.

        Converse com alguém que faz esse trabalho e pergunte como é a semana
        mais difícil dela.
        TEXT;

        $this->assertSame([], RedTeamLint::inspect($spanish));
        $this->assertSame([], RedTeamLint::inspect($portuguese));
    }
}
