<?php

namespace Tests\Feature;

use App\Support\NarrativeLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proven against the corpus itself rather than against strings written to
 * pass. Every narrative in `tests/Fixtures/Engine` declares the locale it was
 * written in, so the fixtures are the honest test set — and if a fixture is
 * ever added in a language this cannot read, that fails here first.
 */
class NarrativeLanguageTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function narratives(): array
    {
        $cases = [];

        foreach (glob(dirname(__DIR__).'/Fixtures/Engine/*.json') as $path) {
            $fixture = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

            $cases[$fixture['name']] = [$fixture['narrative'], $fixture['locale'] ?? 'en-US'];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('narratives')]
    public function the_corpus_narratives_are_read_as_the_language_they_were_written_in(string $narrative, string $locale): void
    {
        $this->assertSame(
            NarrativeLanguage::expectedFor($locale),
            NarrativeLanguage::of($narrative),
        );
    }

    /**
     * The confusable pair. Spanish and Portuguese share most of their
     * vocabulary, so a check that cannot separate them would pass the whole
     * corpus while being useless for the one thing it exists to catch.
     */
    #[Test]
    public function spanish_is_not_read_as_portuguese_or_the_reverse(): void
    {
        $spanish = 'Lo que dijiste sobre el trabajo aparece otra vez, pero esto es una observación, no una conclusión. Vale la pena hablar con alguien antes de decidir nada.';
        $portuguese = 'O que você escreveu sobre o trabalho aparece de novo, mas isso é uma observação, não uma conclusão. Vale a pena conversar com alguém da sua área antes de decidir.';

        $this->assertSame('es', NarrativeLanguage::of($spanish));
        $this->assertSame('pt', NarrativeLanguage::of($portuguese));
    }

    /**
     * The failure this exists for: the engine is told the student wrote in
     * Spanish and answers in English anyway. Every other assertion in a live
     * run would still pass.
     */
    #[Test]
    public function an_english_answer_to_a_spanish_student_is_visible(): void
    {
        $english = 'What you said about caring for your grandmother appears three times, and each time you describe what you did rather than what you felt.';

        $this->assertSame('en', NarrativeLanguage::of($english));
        $this->assertNotSame(NarrativeLanguage::expectedFor('es-419'), NarrativeLanguage::of($english));
    }

    /**
     * Declines rather than guesses. A confident wrong answer would fail a
     * live run for the wrong reason.
     */
    #[Test]
    public function nothing_to_go_on_is_not_a_guess(): void
    {
        $this->assertNull(NarrativeLanguage::of(''));
        $this->assertNull(NarrativeLanguage::of('12345 !!! ---'));
    }

    /**
     * Both spellings, because only one of them exercises the fold. The
     * markers are stored unaccented, so the *accented* form — the one a
     * model actually writes — is the one that needs folding to match at all.
     * A test that passes only the stripped spelling proves nothing.
     */
    #[Test]
    public function a_missing_accent_does_not_change_the_language(): void
    {
        foreach ([
            'Você não é só o que você já fez, e não é uma conclusão.',
            'Voce nao e so o que voce ja fez, e nao e uma conclusao.',
        ] as $narrative) {
            $this->assertSame('pt', NarrativeLanguage::of($narrative), "Not read as Portuguese: {$narrative}");
        }
    }

    /**
     * The two mechanisms, separated.
     *
     * The first pair shares its function words and diverges only in the
     * `-ción`/`-ção` ending; the second carries no such ending at all and
     * must be separated by function words alone. Together they mean neither
     * mechanism can be deleted without a test failing — the first version of
     * this file had fixtures that both mechanisms happened to solve, so
     * either could be removed and everything still passed.
     */
    #[Test]
    public function the_ending_alone_can_tell_them_apart(): void
    {
        $this->assertSame('es', NarrativeLanguage::of('Una observación, una conclusión, una situación.'));
        $this->assertSame('pt', NarrativeLanguage::of('Uma observação, uma conclusão, uma situação.'));
    }

    /**
     * The check only matters where it runs. Nothing in the offline suite
     * exercises the live harness — it needs a model — so the one thing that
     * can be asserted about it here is that it still asks the question. A
     * silent deletion of that assertion would otherwise cost nothing until a
     * Spanish student received a fluent English portrait.
     */
    #[Test]
    public function the_live_harness_checks_the_language(): void
    {
        $harness = (string) file_get_contents(dirname(__DIR__).'/Live/EngineFixtureLiveTest.php');

        $this->assertStringContainsString('NarrativeLanguage::of($prose)', $harness);
        $this->assertStringContainsString('NarrativeLanguage::expectedFor(', $harness);
    }

    #[Test]
    public function the_function_words_alone_can_tell_them_apart(): void
    {
        $this->assertSame('es', NarrativeLanguage::of('El trabajo del que hablaste, pero esto vale la pena antes de nada.'));
        $this->assertSame('pt', NarrativeLanguage::of('O trabalho da sua area que voce falou, mas isso vale a pena sobre o seu dia.'));
    }
}
