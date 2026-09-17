<?php

namespace App\Support;

/**
 * Blueprint Layer 8: the red-team pass over user-facing language.
 *
 * Most of blueprint §10.4 and §13 is a word list, and a word list does not
 * need a model to enforce it. Running it deterministically means the rule is
 * applied identically every time, costs nothing, and cannot be talked out of
 * by a persuasive completion.
 *
 * Severity is the whole design. Two failure modes are not alike:
 *
 * - **Blocking** — language the product must never show a student. Claiming
 *   divine instruction, asserting destiny, diagnosing, or reducing a person to
 *   a percentage. These are the failures §11 calls identity foreclosure, and a
 *   missing result is better than one of them.
 * - **Warning** — language that is off-voice but not harmful: flattery,
 *   corporate-speak, hype. Worth logging and fixing, never worth withholding
 *   someone's result over.
 *
 * Note this lints English. A narrative generated in another locale will pass
 * the phrase checks trivially; that is a known gap, not a solved problem.
 */
class RedTeamLint
{
    public const BLOCKING = 'blocking';

    public const WARNING = 'warning';

    /**
     * Phrases that claim divine instruction. The blueprint is unambiguous:
     * the system may help someone discern, and may never speak for God.
     */
    protected const DIVINE_PRESUMPTION = [
        'god has called you', 'god is calling you', 'god is telling you',
        'god wants you to', 'god made you to', 'god created you to',
        'the lord is calling you', 'the lord has called you',
        'this is your calling', 'this is god\'s plan for you',
    ];

    /**
     * Determinism — the tone that forecloses identity. A young person will
     * organize themselves around an authority-sounding sentence.
     */
    protected const DETERMINISM = [
        'destiny', 'destined', 'meant to be', 'born for', 'born to be',
        'fate', 'fated', 'the only path', 'your one true', 'you should become',
        'you will be successful', 'you were made for',
        // Found by the golden corpus: a sentence that tells a student what
        // they *are* forecloses harder than one that tells them what to do,
        // and none of the phrases above reach it.
        'this is who you are', 'that is who you are',
    ];

    /**
     * False certainty. The engine's honesty depends on it never overstating
     * what the evidence carries.
     */
    protected const FALSE_CERTAINTY = [
        'guaranteed', 'perfect fit', 'perfect career', 'unquestionably',
        'ultimate purpose', 'without a doubt', 'there is no question',
        // Also from the corpus. "without a doubt" was listed but the far more
        // natural "there is no doubt" was not, and a promise about the future
        // ("you will certainly thrive") is the form this actually takes in
        // prose rather than in a word list.
        'there is no doubt',
        '/\byou will (?:certainly|definitely|absolutely|undoubtedly)\b/u',
    ];

    protected const MYSTICISM = [
        'manifest your', 'vibration', 'cosmic', 'the universe wants',
        'the universe is telling', 'written in the stars',
    ];

    /**
     * Clinical language, and why it needs more than a word list.
     *
     * The invariant is that the product never diagnoses the *student*. It is
     * not that clinical vocabulary is unsayable. Healthcare is one of the
     * seventeen pathways, and a student drawn to it cannot be told about
     * diagnostic work, pathology, or speech disorders without the words
     * appearing — so a bare stem match refuses those students a result at all.
     *
     * That is not hypothetical. It is how this was found: a live narrative for
     * a student who wrote about loving "when nobody knew what was wrong with
     * someone yet" was blocked twice on the stem "diagnos", and the repair
     * instruction handed the model the string "diagnos", which appears nowhere
     * in its draft and so could not be acted on.
     *
     * A clinical term therefore only counts when the sentence **attributes the
     * condition to the student** — a possessive, or a second-person predicate
     * taking the condition as its object. Naming a field of medicine, or a
     * population someone might serve, is not a diagnosis of anyone.
     */
    protected const CLINICAL = [
        // "your diagnosis", "your symptoms", "your possible mood disorder".
        '/\byour\s+(?:\w+\s+){0,2}(diagnos\w*|disorders?|syndromes?|symptoms?|condition)\b/u',
        // "you have been diagnosed", "you may have a speech disorder".
        '/\byou\s+(?:may |might |could |probably |likely )?(?:have|had|show|exhibit|display|suffer from)\s+(?:been |a |an |some |signs of )*(?:\w+\s+){0,2}(diagnos\w*|disorders?|syndromes?)\b/u',
        // "we can diagnose you", "the assessment diagnosed you".
        '/\bdiagnos(?:e|ed|ing)\s+you\b/u',
        // Attributing a condition to the instrument's own output. The windows
        // are tight on purpose: "your answers suggest an anxiety disorder" is
        // a diagnosis, where "your answers suggest an interest in speech
        // disorders" is a pathway, and only distance separates them.
        '/\b(?:your answers|your responses|this profile|these results|this assessment)\b[^.!?]{0,30}\b(?:suggests?|indicates?|reveals?|points? to)\b[^.!?]{0,20}\b(?:diagnos(?:is|ed)|disorders?|syndromes?)\b/u',
        // The clinical-inference sense, which survives the occupational strip.
        '/\bdiagnostic of\b/u',
        'pathological',
        'mental health condition',
        'trauma response',
        'attachment style',
        'personality type indicates',
    ];

    /**
     * Occupational forms, stripped before the clinical rule runs.
     *
     * These name a field, a practitioner or a place of work. "A career in
     * diagnostic imaging" is a pathway; it is not a claim about the reader.
     *
     * "Diagnostic **of**" is deliberately left standing. That construction is
     * only ever the clinical-inference sense — "this is diagnostic of a
     * caregiving pattern" is the instrument reading the student — where
     * "diagnostic work" and "diagnostic imaging" name a job.
     */
    protected const CLINICAL_OCCUPATIONAL = [
        '/\bdiagnostics?\b(?! of)/u',
        '/\bdiagnosticians?\b/u',
        '/\bdiagnostically\b/u',
        '/\bpatholog(?:y|ist|ists|ies)\b/u',
    ];

    /**
     * Blueprint §10.5. Low confidence is never a dead end: the system says
     * what it does not yet know and what would resolve it.
     */
    protected const DEAD_END = [
        'not enough information to help',
        'unable to help you',
        'cannot determine your',
        'we could not find a match',
    ];

    protected const FLATTERY = [
        'genius', 'extraordinary', 'world-changing', 'limitless',
        'one in a million', 'exceptional talent',
    ];

    protected const CORPORATE = [
        'optimize', 'leverage', 'maximize', 'human capital',
        'productivity profile', 'career asset', 'roi', 'synergy',
    ];

    protected const HYPE = ['dream job', 'ideal career', 'dream career'];

    /**
     * The same nine rules in the other two languages the product speaks.
     *
     * Written **without accents**, because the haystack is accent-folded
     * before matching ({@see static::fold()}). A model drops a tilde far more
     * often than it drops a claim, and "Dios te ha creado para" must not pass
     * because it arrived as "Dios te ha creado para" with a plain a.
     *
     * Phrases are longer here than in the English lists on purpose. Single
     * words that are damning in English are ordinary in Spanish and
     * Portuguese: "destino" is where a bus goes, "genial" is how a Tuesday
     * was. Each entry therefore carries the second person with it, which is
     * the thing that turns an observation into a verdict on the reader.
     */
    protected const DIVINE_PRESUMPTION_ES_PT = [
        'dios te ha llamado', 'dios te llama', 'dios te esta llamando',
        'dios te creo para', 'dios te hizo para', 'dios quiere que',
        'el senor te ha llamado', 'el senor te llama',
        'este es tu llamado', 'es el plan de dios para ti', 'el plan de dios para tu vida',
        'deus te chamou', 'deus te chama', 'deus esta te chamando',
        'deus te criou para', 'deus te fez para', 'deus quer que voce',
        'o senhor te chamou', 'o senhor te chama',
        'este e o seu chamado', 'e o plano de deus para voce', 'o plano de deus para a sua vida',
    ];

    protected const DETERMINISM_ES_PT = [
        'tu destino es', 'su destino es', 'estas destinado a', 'estas destinada a',
        'naciste para', 'fuiste creado para', 'fuiste creada para',
        'el unico camino para ti', 'deberias ser', 'esto es quien eres',
        'seu destino e', 'voce esta destinado a', 'voce esta destinada a',
        'voce nasceu para', 'voce foi criado para', 'voce foi criada para',
        'o unico caminho para voce', 'voce deveria ser', 'isso e quem voce e',
        // Found by the golden corpus the day Spanish was added: the English
        // list was translated phrase by phrase, and 'this is who you are'
        // became only 'esto es quien eres'. Spanish reaches for *lo que*
        // where English reaches for *who*, so the natural way to write the
        // banned sentence was the one spelling the list did not hold.
        'esto es lo que eres', 'eso es lo que eres', 'eso es quien eres',
        'isso e o que voce e', 'e isso o que voce e',
    ];

    protected const FALSE_CERTAINTY_ES_PT = [
        'garantizado', 'carrera perfecta', 'trabajo perfecto', 'encaje perfecto',
        'sin lugar a dudas', 'no hay ninguna duda', 'no cabe duda',
        'tu proposito ultimo', 'sin duda alguna',
        'garantido', 'carreira perfeita', 'trabalho perfeito', 'encaixe perfeito',
        'sem sombra de duvida', 'nao ha duvida', 'nao resta duvida',
        'seu proposito final', 'sem duvida alguma',
    ];

    protected const MYSTICISM_ES_PT = [
        'el universo quiere', 'el universo te esta diciendo', 'escrito en las estrellas',
        'manifiesta tu', 'tu vibracion', 'energia cosmica',
        'o universo quer', 'o universo esta te dizendo', 'escrito nas estrelas',
        'manifeste o seu', 'sua vibracao', 'energia cosmica',
    ];

    protected const DEAD_END_ES_PT = [
        'no hay suficiente informacion para ayudar', 'no podemos ayudarte',
        'no pudimos encontrar', 'no se puede determinar tu',
        'nao ha informacao suficiente para ajudar', 'nao podemos te ajudar',
        'nao encontramos nenhuma', 'nao e possivel determinar seu',
    ];

    protected const FLATTERY_ES_PT = [
        'eres un genio', 'talento excepcional', 'uno en un millon', 'extraordinario',
        'voce e um genio', 'talento excepcional', 'um em um milhao', 'extraordinario',
    ];

    protected const CORPORATE_ES_PT = [
        'optimizar', 'maximizar', 'capital humano', 'sinergia', 'activo profesional',
        'otimizar', 'ativo profissional',
    ];

    protected const HYPE_ES_PT = [
        'trabajo de tus suenos', 'carrera de tus suenos', 'carrera ideal',
        'emprego dos seus sonhos', 'carreira dos seus sonhos', 'carreira ideal',
    ];

    protected const OBJECTIFYING_ES_PT = [
        '/\bel sujeto\b/u', '/\bla persona evaluada\b/u', '/\bel candidato obtuvo\b/u',
        '/\bo sujeito\b/u', '/\ba pessoa avaliada\b/u', '/\bo candidato obteve\b/u',
    ];

    /**
     * Clinical attribution in Spanish and Portuguese.
     *
     * Same rule as {@see static::CLINICAL}: naming a field of medicine is a
     * pathway, and only a sentence that hangs the condition on the reader is a
     * diagnosis.
     */
    protected const CLINICAL_ES_PT = [
        '/\btu\s+(?:\w+\s+){0,2}(diagnostico|trastornos?|sindromes?|sintomas?)\b/u',
        '/\bseu\s+(?:\w+\s+){0,2}(diagnostico|transtornos?|sindromes?|sintomas?)\b/u',
        '/\btienes\s+(?:un |una |algun |alguna |signos de )*(?:\w+\s+){0,2}(trastornos?|sindromes?)\b/u',
        '/\bvoce tem\s+(?:um |uma |algum |alguma |sinais de )*(?:\w+\s+){0,2}(transtornos?|sindromes?)\b/u',
    ];

    /**
     * Speaking *about* the person rather than *to* them.
     *
     * "The subject" needs a lookahead: a student writing about the subject
     * matter they love is the opposite of the failure being caught here.
     */
    protected const OBJECTIFYING = [
        '/\bthe subject\b(?! matter| line| of)/u',
        '/\bthis subject\b(?! matter)/u',
        '/\bthe respondent\b/u',
        '/\bthe candidate scored\b/u',
    ];

    /**
     * Every rule as [phrases, severity, rule name].
     *
     * @return array<int, array{0: array<int, string>, 1: string, 2: string}>
     */
    protected static function rules(): array
    {
        /*
         | Every language's list runs against every narrative, regardless of
         | the locale it was generated in. A draft that switches language
         | mid-paragraph is exactly where a banned claim hides, and a lint that
         | reads only the language on the label is a lint that can be walked
         | around. Same reasoning as {@see CrisisCheck}.
         */
        return [
            [[...static::DIVINE_PRESUMPTION, ...static::DIVINE_PRESUMPTION_ES_PT], static::BLOCKING, 'divine_presumption'],
            [[...static::DETERMINISM, ...static::DETERMINISM_ES_PT], static::BLOCKING, 'determinism'],
            [[...static::FALSE_CERTAINTY, ...static::FALSE_CERTAINTY_ES_PT], static::BLOCKING, 'false_certainty'],
            [[...static::MYSTICISM, ...static::MYSTICISM_ES_PT], static::BLOCKING, 'mysticism'],
            [[...static::DEAD_END, ...static::DEAD_END_ES_PT], static::BLOCKING, 'dead_end'],
            [[...static::FLATTERY, ...static::FLATTERY_ES_PT], static::WARNING, 'flattery'],
            [[...static::CORPORATE, ...static::CORPORATE_ES_PT], static::WARNING, 'corporate_speak'],
            [[...static::HYPE, ...static::HYPE_ES_PT], static::WARNING, 'hype'],
            [[...static::OBJECTIFYING, ...static::OBJECTIFYING_ES_PT], static::WARNING, 'objectifying'],
        ];
    }

    /**
     * The languages this lint can actually read.
     *
     * A false clean is strictly worse than no lint, because Layer 8 is the
     * gate the rest of the engine trusts, and `assessments.locale` has been a
     * real column since the schema was written. So a narrative in a language
     * with no rules behind it is refused rather than passed.
     *
     * Spanish and Portuguese were added once the phrase lists existed for
     * them — the same three languages {@see ConversationLocale::supported()}
     * lists, which is not a coincidence: a locale the product will speak in
     * and cannot red-team in is a locale it should not speak in.
     *
     * @var list<string>
     */
    public const COVERED_LANGUAGES = ['en', 'es', 'pt'];

    /**
     * Whether a narrative in this locale can be red-teamed at all.
     *
     * A null or empty locale is the application default, which is English.
     */
    public static function covers(?string $locale): bool
    {
        if (blank($locale)) {
            return true;
        }

        $language = strtolower(explode('-', str_replace('_', '-', $locale))[0]);

        return in_array($language, static::COVERED_LANGUAGES, true);
    }

    /**
     * Why a narrative in this locale cannot ship.
     *
     * Phrased as a refusal rather than a warning because there is no safe
     * degraded mode: the alternative to refusing is shipping a minor a
     * narrative nobody checked for "God has called you to" in their own
     * language.
     */
    public static function unverifiableLanguageReason(?string $locale): string
    {
        $covered = implode(', ', static::COVERED_LANGUAGES);

        return "The red-team lint only reads: {$covered}. A narrative in '{$locale}' cannot be checked for the "
            .'language this product must never use, and an unchecked narrative is not shipped.';
    }

    /**
     * Lint a body of user-facing text.
     *
     * @return array<int, array{rule: string, severity: string, match: string}>
     */
    public static function inspect(string $text): array
    {
        $haystack = mb_strtolower($text);
        $findings = [];

        foreach (static::rules() as [$phrases, $severity, $rule]) {
            foreach ($phrases as $phrase) {
                $match = static::matchedText($haystack, $phrase);

                if ($match !== null) {
                    $findings[] = ['rule' => $rule, 'severity' => $severity, 'match' => $match];
                }
            }
        }

        foreach (static::clinicalAttributions($haystack) as $match) {
            $findings[] = ['rule' => 'clinical', 'severity' => static::BLOCKING, 'match' => $match];
        }

        foreach (static::mechanicalScores($haystack) as $match) {
            $findings[] = ['rule' => 'mechanical_score', 'severity' => static::BLOCKING, 'match' => $match];
        }

        return $findings;
    }

    /**
     * Clinical language that is predicated of the student.
     *
     * Occupational forms are removed first rather than excluded by lookahead,
     * because the same sentence often carries both: "you have a real pull
     * toward diagnostic work" contains a second-person predicate *and* a
     * clinical stem, and is exactly the sentence the product exists to write.
     *
     * The offending phrase is returned rather than the rule that caught it, so
     * {@see static::repairInstruction()} can quote back text the model can
     * actually find in its own draft.
     *
     * @return array<int, string>
     */
    protected static function clinicalAttributions(string $haystack): array
    {
        $vocational = static::fold(preg_replace(static::CLINICAL_OCCUPATIONAL, ' ', $haystack) ?? $haystack);

        $found = [];

        foreach ([...static::CLINICAL, ...static::CLINICAL_ES_PT] as $phrase) {
            if (! str_starts_with($phrase, '/')) {
                if (static::contains($vocational, $phrase)) {
                    $found[] = $phrase;
                }

                continue;
            }

            if (preg_match($phrase, $vocational, $matches) === 1) {
                $found[] = trim($matches[0]);
            }
        }

        return $found;
    }

    /**
     * Reducing a person to a number is one of the nine tones §10.4 forbids,
     * and the design system bans percentages and match scores outright.
     *
     * A bare number is fine — someone can be seventeen, or name three steps.
     * What is forbidden is a number presented as a measure of *them*.
     *
     * @return array<int, string>
     */
    protected static function mechanicalScores(string $haystack): array
    {
        $patterns = [
            '/\d+\s?%/u',
            '/\b\d+\s*(?:\/|out of)\s*(?:10|100)\b/u',
            '/\bmatch score\b/u',
            '/\bcompatibility score\b/u',
            '/\bscored?\s+\d+\s+(?:points?|on)\b/u',
        ];

        $found = [];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack, $matches) === 1) {
                $found[] = trim($matches[0]);
            }
        }

        return $found;
    }

    /**
     * Match on word boundaries so a banned term is not found inside an
     * innocent longer word. "Fate" must not fire on "fateful"; "roi" must not
     * fire on every "heroic".
     *
     * An entry may also be a full regex, for the phrases where a plain match
     * is too blunt to be safe.
     */
    protected static function contains(string $haystack, string $phrase): bool
    {
        return static::matchedText($haystack, $phrase) !== null;
    }

    /**
     * The offending text itself, or null when the phrase does not appear.
     *
     * A pattern entry must report what it *matched*, never the pattern. The
     * findings are quoted back to the model by {@see static::repairInstruction()},
     * and handing it a regex it cannot find anywhere in its own draft is an
     * instruction it cannot act on — the same failure that made the clinical
     * rule unrepairable on the first live run.
     */
    protected static function matchedText(string $haystack, string $phrase): ?string
    {
        if (! str_starts_with($phrase, '/')) {
            $quoted = preg_quote($phrase, '/');
            $trailing = preg_match('/[a-z]$/u', $phrase) === 1 ? '\b' : '';
            $phrase = "/\b{$quoted}{$trailing}/u";
        }

        $folded = static::fold($haystack);

        if (preg_match($phrase, $folded, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        [$match, $byteOffset] = $matches[0];

        /*
         | Report the span as the model wrote it, accents and all. Folding
         | exists so the match cannot be dodged with a missing tilde; quoting
         | the folded text back would hand the model a string it cannot find
         | in its own draft, which is the failure that made the clinical rule
         | unrepairable on the first live run.
         |
         | Safe because {@see static::fold()} replaces one character with one
         | character, so a character offset in the folded text is the same
         | character offset in the original.
         */
        $characterOffset = mb_strlen(substr($folded, 0, $byteOffset));

        return mb_substr($haystack, $characterOffset, mb_strlen($match));
    }

    /**
     * Strip the accents, one character in for one character out.
     *
     * The rules for Spanish and Portuguese are written unaccented and matched
     * against this, because a model drops a tilde far more readily than it
     * drops a claim — and "nao ha duvida" is the same promise as "não há
     * dúvida". The one-for-one property is load-bearing: {@see
     * static::matchedText()} maps an offset in the folded text straight back
     * into the original.
     */
    protected static function fold(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    /**
     * @param  array<int, array{rule: string, severity: string, match: string}>  $findings
     * @return array<int, array{rule: string, severity: string, match: string}>
     */
    public static function blocking(array $findings): array
    {
        return array_values(array_filter(
            $findings,
            static fn (array $finding): bool => $finding['severity'] === static::BLOCKING,
        ));
    }

    /**
     * @param  array<int, array{rule: string, severity: string, match: string}>  $findings
     * @return array<int, array{rule: string, severity: string, match: string}>
     */
    public static function warnings(array $findings): array
    {
        return array_values(array_filter(
            $findings,
            static fn (array $finding): bool => $finding['severity'] === static::WARNING,
        ));
    }

    public static function passes(string $text): bool
    {
        return static::blocking(static::inspect($text)) === [];
    }

    /**
     * A one-line instruction naming what must be removed, for handing back to
     * a model on a repair attempt.
     *
     * @param  array<int, array{rule: string, severity: string, match: string}>  $findings
     */
    public static function repairInstruction(array $findings): string
    {
        $matches = collect(static::blocking($findings))
            ->pluck('match')
            ->unique()
            ->map(fn (string $match) => "\"{$match}\"")
            ->implode(', ');

        return <<<REPAIR
        Your previous draft contained language this product must never use: {$matches}.

        Rewrite it without those phrases and without any equivalent. Do not
        claim to speak for God, do not assert destiny or certainty, do not use
        clinical or diagnostic language, and never reduce this person to a
        number or a percentage. Say what the evidence suggests and what would
        test it. Keep every section and keep the substance; change only the
        language that overstates.
        REPAIR;
    }
}
