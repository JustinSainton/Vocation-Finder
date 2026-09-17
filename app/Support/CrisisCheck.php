<?php

namespace App\Support;

use App\Ai\Agents\PathwayCoachAgent;
use App\Enums\CrisisStanding;

/**
 * The one thing in this product that outranks the product.
 *
 * The blueprint's safety section is unambiguous: crisis content reaches human
 * support **before** it reaches vocational interpretation. Until now that rule
 * lived only in {@see PathwayCoachAgent}'s prompt, which is to
 * say it lived nowhere — a prompt is a request, and the one failure mode that
 * must not depend on a model behaving is this one.
 *
 * So it is a lint, for the same reasons as {@see RedTeamLint} and
 * {@see JobVetting} and one more that only applies here: a model asked "is
 * this a crisis?" is being asked to make a clinical judgement, and it will
 * sometimes decide no in order to be helpful about the question it was
 * actually asked. This class cannot be persuaded, costs nothing, and runs
 * before the network call rather than after it.
 *
 * **It does not measure severity.** {@see CrisisStanding} has two cases. A
 * scale would be a diagnosis, which the safety invariant forbids, and it would
 * invite a graded response — a little bit of coaching for a little bit of
 * distress.
 *
 * **It is deliberately over-eager.** The two errors are not comparable. A
 * false positive costs a student one reply about careers that they instead get
 * a phone number in; a false negative is the failure this product would not
 * come back from. Past-tense and third-party disclosures both escalate: a
 * student telling us their friend said this is a student who needs to know
 * where to send them.
 *
 * **Escalation is not a report.** Nobody is notified. Parents never see
 * coaching conversations, and a tool that silently told a school what a
 * sixteen-year-old typed would be the last thing any of them typed honestly.
 * What escalation means is that the conversation stops being about careers and
 * the student is handed a real way to reach a person.
 */
class CrisisCheck
{
    /**
     * Phrases in the three locales the product supports
     * ({@see ConversationLocale::supported()}), matched after accents are
     * folded, because a student in distress is not checking their diacritics.
     *
     * Multi-word spans rather than single words throughout. "Die", "hurt" and
     * "end" are ordinary words in an assessment about work; "want to die" is
     * not a sentence anybody writes about a career.
     */
    protected const PHRASES = [
        'en-US' => [
            'kill myself', 'killing myself', 'kill herself', 'kill himself', 'kill themselves',
            'end my life', 'ending my life', 'take my own life', 'taking my own life',
            'want to die', 'wants to die', 'wanna die', 'want to be dead', 'wish i was dead',
            'wish i were dead', 'better off dead', 'better off without me',
            'suicidal', 'suicide', 'commit suicide',
            'hurt myself', 'hurting myself', 'harm myself', 'harming myself', 'self harm',
            'cut myself', 'cutting myself',
            // First person only. A bare "overdose" is a documentary somebody
            // watched, and the word is why half of them want to be nurses.
            'i overdosed', 'took an overdose', 'take an overdose', 'od myself',
            'no reason to live', 'nothing to live for', 'cant go on', 'can not go on',
            'dont want to be here anymore', 'do not want to be here anymore',
            'dont want to live', 'do not want to live',
            'nobody would miss me', 'no one would miss me',
            // Abuse and violence at home. Not self-harm, same rule: a person
            // first, and nothing about careers until then.
            'hits me', 'hitting me', 'beats me', 'hurts me at home',
            'touched me', 'raped me', 'sexually assaulted', 'sexual assault',
            'not safe at home', 'im not safe', 'i am not safe', 'afraid to go home',
            'nowhere to sleep', 'nowhere to live', 'kicked me out', 'im homeless', 'i am homeless',
            'not eaten in', 'havent eaten in', 'have not eaten in',
        ],
        'es-419' => [
            'matarme', 'me quiero matar', 'quiero matarme', 'suicidarme', 'suicidio', 'suicida',
            'quitarme la vida', 'acabar con mi vida', 'terminar con mi vida',
            'quiero morir', 'quiero morirme', 'desearia estar muerto', 'desearia estar muerta',
            'mejor muerto', 'mejor muerta', 'estarian mejor sin mi',
            'hacerme dano', 'lastimarme', 'cortarme',
            'no quiero vivir', 'no quiero seguir', 'no puedo mas', 'ya no puedo mas',
            'no tengo razones para vivir', 'nadie me extranaria',
            'me pega', 'me pegan', 'me golpea', 'me golpean', 'abusaron de mi', 'me violaron',
            'no estoy segura en casa', 'no estoy seguro en casa', 'me corrieron de la casa',
            'no tengo donde dormir', 'no tengo donde vivir', 'no he comido en',
        ],
        'pt-BR' => [
            'me matar', 'quero me matar', 'suicidio', 'suicida', 'me suicidar',
            'tirar minha vida', 'acabar com a minha vida', 'acabar com minha vida',
            'quero morrer', 'queria estar morto', 'queria estar morta',
            'melhor morto', 'melhor morta', 'ficariam melhor sem mim',
            'me machucar', 'me cortar', 'me ferir',
            'nao quero viver', 'nao quero mais viver', 'nao aguento mais',
            'sem motivo para viver', 'ninguem sentiria minha falta',
            'me bate', 'me batem', 'abusaram de mim', 'me estupraram',
            'nao estou segura em casa', 'nao estou seguro em casa', 'me expulsaram de casa',
            'nao tenho onde dormir', 'nao tenho onde morar', 'nao como ha',
        ],
    ];

    /**
     * Whether this text stops the conversation.
     *
     * Every locale's phrases are checked regardless of which locale the text
     * was written in. A bilingual student switches language mid-sentence for
     * exactly the things that are hardest to say, and reading the wrong list
     * because a dropdown says "English" would be the worst possible moment to
     * be tidy about it.
     */
    public function standing(string $text): CrisisStanding
    {
        $normalized = static::normalize($text);

        foreach (static::PHRASES as $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, ' '.$phrase.' ')) {
                    return CrisisStanding::Escalate;
                }
            }
        }

        return CrisisStanding::None;
    }

    /**
     * Lowercased, accent-folded, punctuation-flattened, space-padded.
     *
     * The padding is what makes `str_contains` a whole-word match: a phrase
     * has to sit between spaces, so "overdose" does not fire inside another
     * word. That is worth the cost of listing inflections by hand — matching
     * inside words is how a lint ends up escalating on "assessment" because it
     * contains "ass", and a crisis message that arrives for no reason teaches
     * a student that this one is noise.
     */
    protected static function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            '’' => '', '\'' => '',
        ]);

        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return ' '.trim(preg_replace('/\s+/u', ' ', $text) ?? $text).' ';
    }

    /**
     * What we say instead of coaching.
     *
     * Fixed text, never generated, for the same reason as
     * {@see ConversationPrompts}: this is the one message in the product that
     * has to be right every single time, and a model that writes it fresh each
     * turn will eventually write it badly.
     *
     * The resources are United States services, because this product is sold
     * to United States schools and its whole campus layer assumes it. Adding a
     * country means adding that country's verified numbers — never a guess,
     * for the same reason {@see CollegeExplorer} refuses invented net prices.
     * A wrong crisis number is worse than no number.
     *
     * @return array{heading: string, body: list<string>, resources: list<array{name: string, contact: string, note: string}>}
     */
    public function support(string $locale = ConversationLocale::DEFAULT): array
    {
        return match (ConversationLocale::normalize($locale)) {
            'es-419' => [
                'heading' => 'Esto importa más que cualquier conversación sobre carreras.',
                'body' => [
                    'Gracias por escribirlo. Voy a dejar el tema de las carreras a un lado por ahora.',
                    'Hablar con una persona ayuda más que cualquier cosa que yo pueda decirte. Estas líneas son gratuitas, funcionan las 24 horas y también atienden si te preocupa otra persona.',
                ],
                'resources' => static::resources('es-419'),
            ],
            'pt-BR' => [
                'heading' => 'Isto é mais importante do que qualquer conversa sobre carreira.',
                'body' => [
                    'Obrigado por escrever isso. Vou deixar o assunto de carreira de lado por enquanto.',
                    'Falar com uma pessoa ajuda mais do que qualquer coisa que eu possa dizer. Estas linhas são gratuitas, funcionam 24 horas e também atendem se a sua preocupação for com outra pessoa.',
                ],
                'resources' => static::resources('pt-BR'),
            ],
            default => [
                'heading' => 'This matters more than anything we were talking about.',
                'body' => [
                    'Thank you for writing it down. I am setting the careers conversation aside for now.',
                    'Talking to a person helps more than anything I can say. These lines are free, open all day and night, and they will talk to you whether this is about you or about someone you are worried about.',
                ],
                'resources' => static::resources('en-US'),
            ],
        };
    }

    /**
     * @return list<array{name: string, contact: string, note: string}>
     */
    protected static function resources(string $locale): array
    {
        return match ($locale) {
            'es-419' => [
                ['name' => '988 Línea de Prevención del Suicidio y Crisis', 'contact' => 'Llama o envía un mensaje al 988', 'note' => 'Marca 988 y oprime 2 para atención en español.'],
                ['name' => 'Crisis Text Line', 'contact' => 'Envía AYUDA al 741741', 'note' => 'Por mensaje de texto, si hablar te cuesta.'],
                ['name' => 'Emergencias', 'contact' => '911', 'note' => 'Si tú o alguien más está en peligro ahora mismo.'],
            ],
            'pt-BR' => [
                ['name' => '988 Suicide & Crisis Lifeline', 'contact' => 'Ligue ou envie mensagem para 988', 'note' => 'Atendimento em inglês e espanhol, 24 horas.'],
                ['name' => 'Crisis Text Line', 'contact' => 'Envie HOME para 741741', 'note' => 'Por mensagem, se falar for difícil.'],
                ['name' => 'Emergência', 'contact' => '911', 'note' => 'Se você ou outra pessoa estiver em perigo agora.'],
            ],
            default => [
                ['name' => '988 Suicide & Crisis Lifeline', 'contact' => 'Call or text 988', 'note' => 'Free and open every hour of every day.'],
                ['name' => 'Crisis Text Line', 'contact' => 'Text HOME to 741741', 'note' => 'By text, if talking is hard.'],
                ['name' => 'Emergency services', 'contact' => '911', 'note' => 'If you or someone else is in danger right now.'],
            ],
        };
    }
}
