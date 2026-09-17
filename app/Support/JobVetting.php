<?php

namespace App\Support;

use App\Enums\VettingStatus;
use App\Models\JobListing;

/**
 * The vetting pass, run deterministically over every listing.
 *
 * The vision is blunt about why this exists: "not just a dump of every posting
 * on Indeed, because a lot of that is spam. I want a vetted process that
 * filters out spam job posts and eliminates them from the site."
 *
 * It is built like {@see RedTeamLint} and for the same reasons — the rules are
 * a phrase list, a phrase list does not need a model, and running it in code
 * means it applies identically every time and cannot be talked out of by a
 * persuasive posting. A model asked "is this a scam?" is a model that can be
 * written *at* by the scammer, who controls the entire input.
 *
 * Severity is split the same way:
 *
 * - **Blocking** — the markers of advance-fee fraud, data harvesting and
 *   trafficking-adjacent recruitment. Any one of these rejects the listing
 *   outright. The asymmetry is deliberate: a real job wrongly rejected costs a
 *   student one opportunity they will never know about, and a fraudulent one
 *   wrongly published costs a sixteen-year-old their bank details or worse.
 * - **Warning** — low-quality but not dangerous: no company URL, no named
 *   employer, a salary range so wide it says nothing. Recorded on the listing,
 *   never a reason to withhold it.
 *
 * Like {@see RedTeamLint}, it reads English, Spanish and Portuguese, and the
 * phrase lists for all three run against every posting whatever language it
 * claims to be in. A posting in a fourth language still passes the phrase
 * checks trivially — but never the structural ones, which are facts about the
 * record and cannot be written around in any language.
 */
class JobVetting
{
    public const BLOCKING = 'blocking';

    public const WARNING = 'warning';

    /**
     * Advance-fee fraud. The oldest job scam there is: the "employer" needs
     * money first, for training, equipment, a background check or a starter
     * kit. No legitimate employer charges an applicant to be hired.
     */
    protected const PAY_TO_WORK = [
        'pay for your training', 'training fee', 'starter kit', 'equipment fee',
        'application fee required', 'pay for the background check',
        'small investment', 'buy your own kit', 'registration fee',
        'deposit required', 'pay a refundable',
    ];

    /**
     * Money-movement roles. Almost always money laundering, and the applicant
     * is the one who is prosecuted.
     */
    protected const MONEY_MULE = [
        'wire transfer', 'western union', 'moneygram', 'money transfer agent',
        'payment processor from home', 'process payments through your',
        'receive packages and reship', 'package forwarding', 'reshipping',
        'cash a check and', 'zelle', 'cash app payments', 'crypto wallet',
    ];

    /**
     * Identity harvesting. A real employer asks for a social security number
     * on a W-4 *after* an offer, never inside a posting or a first message.
     */
    protected const DATA_HARVEST = [
        'send your social security', 'ssn required to apply',
        'send a photo of your id to apply', 'bank account details to apply',
        'provide your bank login', 'driver\'s license number to apply',
        'credit check to apply',
    ];

    /**
     * Unverifiable-employer patterns. Interviews conducted entirely over
     * consumer chat apps and hiring with no call at all are the documented
     * shape of fake-recruiter fraud, which targets teenagers specifically.
     */
    protected const NO_REAL_EMPLOYER = [
        'interview via telegram', 'interview on whatsapp', 'text us to apply',
        'hiring immediately no interview', 'no interview required',
        'google hangouts interview', 'signal app interview',
    ];

    /**
     * Isolation and travel. The recruitment shape that safeguarding guidance
     * treats as a trafficking indicator, and the one a job board for minors
     * must not be the discovery mechanism for.
     */
    protected const ISOLATION = [
        'travel with us', 'live-in position for students', 'relocation required immediately',
        'must be able to travel same day', 'accommodation provided must live on site',
        'no questions asked', 'discreet work', 'must not tell',
    ];

    /**
     * Hype that only ever appears on postings with nothing behind them. On its
     * own this is a warning; the blocking rules above are what reject.
     */
    protected const IMPLAUSIBLE = [
        'earn $500 a day', 'unlimited earning potential', 'be your own boss',
        'no experience necessary earn', 'make money fast', 'financial freedom',
        'work 2 hours a day', 'guaranteed income',
    ];

    /**
     * The same six rules in Spanish and Portuguese, written **unaccented**
     * because the haystack is folded before matching ({@see static::fold()}).
     *
     * Bilingual postings are ordinary in the districts this product is sold
     * to, and a scam translated is still a scam. Two English phrases have no
     * Spanish counterpart here on purpose: "transferencia bancaria" is how
     * payroll itself is described in Spanish, where "wire transfer" is not how
     * it is described in English, so blocking on it would reject every job
     * that mentions direct deposit.
     */
    protected const PAY_TO_WORK_ES_PT = [
        'paga por tu entrenamiento', 'cuota de entrenamiento', 'kit de inicio',
        'tarifa de solicitud', 'deposito requerido', 'pequena inversion', 'cuota de registro',
        'pague pelo treinamento', 'taxa de treinamento', 'kit inicial',
        'taxa de inscricao', 'deposito necessario', 'pequeno investimento', 'taxa de registro',
    ];

    protected const MONEY_MULE_ES_PT = [
        'agente de transferencia de dinero', 'envia el dinero por western union',
        'recibir paquetes y reenviar', 'reenvio de paquetes',
        'procesar pagos desde casa', 'cobrar un cheque y', 'billetera de criptomonedas',
        'agente de transferencia de dinheiro', 'receber pacotes e reenviar',
        'reenvio de pacotes', 'processar pagamentos em casa',
        'descontar um cheque e', 'carteira de criptomoedas',
    ];

    protected const DATA_HARVEST_ES_PT = [
        'envia tu numero de seguro social', 'datos de tu cuenta bancaria para aplicar',
        'foto de tu identificacion para aplicar', 'clave de tu banco',
        'envie seu cpf para se candidatar', 'dados da sua conta bancaria para se candidatar',
        'foto do seu documento para se candidatar', 'senha do seu banco',
    ];

    protected const NO_REAL_EMPLOYER_ES_PT = [
        'entrevista por whatsapp', 'entrevista por telegram', 'escribenos para aplicar',
        'contratacion inmediata sin entrevista', 'sin entrevista requerida',
        'entrevista pelo whatsapp', 'entrevista pelo telegram',
        'mande mensagem para se candidatar', 'contratacao imediata sem entrevista',
        'sem entrevista necessaria',
    ];

    protected const ISOLATION_ES_PT = [
        'viaja con nosotros', 'debes vivir en el lugar', 'mudanza inmediata requerida',
        'trabajo discreto', 'no le digas a nadie',
        'viaje conosco', 'precisa morar no local', 'mudanca imediata necessaria',
        'trabalho discreto', 'nao conte a ninguem',
    ];

    protected const IMPLAUSIBLE_ES_PT = [
        'ingresos ilimitados', 'se tu propio jefe', 'dinero rapido',
        'libertad financiera', 'ingresos garantizados', 'trabaja 2 horas al dia',
        'ganhos ilimitados', 'seja seu proprio chefe', 'dinheiro rapido',
        'liberdade financeira', 'renda garantida', 'trabalhe 2 horas por dia',
    ];

    /**
     * Every finding on one listing.
     *
     * @return list<array{rule: string, severity: string, detail: string}>
     */
    public function inspect(JobListing $listing): array
    {
        $haystack = static::fold(mb_strtolower(implode(' ', array_filter([
            $listing->title,
            $listing->company_name,
            $listing->description_plain ?: strip_tags((string) $listing->description),
        ]))));

        $findings = [];

        $rules = [
            ['pay_to_work', [...self::PAY_TO_WORK, ...self::PAY_TO_WORK_ES_PT], self::BLOCKING, 'Asks the applicant for money. No employer charges you to be hired.'],
            ['money_mule', [...self::MONEY_MULE, ...self::MONEY_MULE_ES_PT], self::BLOCKING, 'Involves moving money or packages on somebody else\'s behalf.'],
            ['data_harvest', [...self::DATA_HARVEST, ...self::DATA_HARVEST_ES_PT], self::BLOCKING, 'Asks for identity or banking details inside the posting.'],
            ['no_real_employer', [...self::NO_REAL_EMPLOYER, ...self::NO_REAL_EMPLOYER_ES_PT], self::BLOCKING, 'Hires without a real conversation, or interviews only through a chat app.'],
            ['isolation', [...self::ISOLATION, ...self::ISOLATION_ES_PT], self::BLOCKING, 'Recruitment language a safeguarding review treats as a warning sign.'],
            ['implausible', [...self::IMPLAUSIBLE, ...self::IMPLAUSIBLE_ES_PT], self::WARNING, 'Promises earnings the work does not support.'],
        ];

        foreach ($rules as [$rule, $phrases, $severity, $detail]) {
            foreach ($phrases as $phrase) {
                if (str_contains($haystack, $phrase)) {
                    $findings[] = ['rule' => $rule, 'severity' => $severity, 'detail' => $detail.' ("'.$phrase.'")'];

                    break;
                }
            }
        }

        return array_merge($findings, $this->structuralFindings($listing));
    }

    /**
     * Strip the accents, one character in for one character out.
     *
     * The Spanish and Portuguese lists are written unaccented and matched
     * against this. A scraped posting has been through an HTML pipeline and a
     * copy-paste before it reaches us, and "reenvío" arriving as "reenvio" is
     * not a reason to publish a reshipping scam to a sixteen-year-old.
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
     * What is missing rather than what is said.
     *
     * A posting with no employer name and no URL cannot be checked by anybody
     * — not by us, not by a counsellor, not by a parent — so it is blocked
     * rather than warned about. Everything here is a fact about the record, so
     * no phrase list can be written around it.
     *
     * @return list<array{rule: string, severity: string, detail: string}>
     */
    protected function structuralFindings(JobListing $listing): array
    {
        $findings = [];

        if (blank($listing->company_name)) {
            $findings[] = [
                'rule' => 'anonymous_employer',
                'severity' => self::BLOCKING,
                'detail' => 'No employer is named, so nobody can check who this is.',
            ];
        }

        if (blank($listing->source_url) && blank($listing->company_url)) {
            $findings[] = [
                'rule' => 'unreachable_employer',
                'severity' => self::BLOCKING,
                'detail' => 'No link to the employer or the original posting, so there is nothing to verify against.',
            ];
        }

        if (blank($listing->description) && blank($listing->description_plain)) {
            $findings[] = [
                'rule' => 'no_description',
                'severity' => self::WARNING,
                'detail' => 'The posting does not say what the work is.',
            ];
        }

        return $findings;
    }

    /**
     * @param  list<array{rule: string, severity: string, detail: string}>  $findings
     * @return list<array{rule: string, severity: string, detail: string}>
     */
    public static function blocking(array $findings): array
    {
        return array_values(array_filter($findings, fn (array $f) => $f['severity'] === self::BLOCKING));
    }

    /**
     * @param  list<array{rule: string, severity: string, detail: string}>  $findings
     * @return list<array{rule: string, severity: string, detail: string}>
     */
    public static function warnings(array $findings): array
    {
        return array_values(array_filter($findings, fn (array $f) => $f['severity'] === self::WARNING));
    }

    /**
     * Inspect and record the verdict on the listing itself.
     */
    public function vet(JobListing $listing): JobListing
    {
        $findings = $this->inspect($listing);

        $listing->forceFill([
            'vetting_status' => static::blocking($findings) === []
                ? VettingStatus::Passed
                : VettingStatus::Rejected,
            'vetting_findings' => $findings,
            'vetted_at' => now(),
        ])->save();

        return $listing;
    }
}
