import AppLayout from '../../Layouts/AppLayout';

interface Step {
    title: string;
    detail: string;
    url: string | null;
    yours: boolean;
}

interface Props {
    listing: {
        id: string;
        title: string;
        company_name: string;
        location: string | null;
        is_remote: boolean;
        kind: string;
        kind_description: string;
        minimum_age: number | null;
        supervised: boolean | null;
        pay: string;
        description: string;
        source_url: string | null;
        company_url: string | null;
    };
    steps: Step[];
    questions: string[];
    conversation: string;
}

/**
 * One posting, and how to go after it.
 *
 * There is no apply button. The steps hand over links and the student does
 * every one of them — same boundary the college walkthrough holds, and for a
 * sharper reason here: an application for work is a thing an employer will
 * later hold the student to.
 */
export default function WorkShow({ listing, steps, questions, conversation }: Props) {
    return (
        <AppLayout title={listing.title}>
            <p className="type-eyebrow">Plan · Work</p>
            <h1 className="mt-2 font-serif text-3xl leading-tight text-[var(--color-text)]">{listing.title}</h1>
            <p className="mt-1 text-[var(--color-text-muted)]">
                {listing.company_name}
                {listing.location ? ` · ${listing.location}` : ''}
                {listing.is_remote ? ' · Remote' : ''}
            </p>

            <p className="mt-6 text-[var(--color-text)]">{listing.pay}</p>
            <p className="mt-1 text-[var(--color-text-secondary)]">{listing.kind_description}</p>
            {listing.minimum_age !== null && (
                <p className="mt-1 text-[var(--color-text-muted)]">You must be {listing.minimum_age} or older for this one.</p>
            )}

            {listing.description && (
                <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                    <p className="type-eyebrow">What they wrote</p>
                    <p className="mt-3 whitespace-pre-line text-[var(--color-text-secondary)]">{listing.description}</p>
                </section>
            )}

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">How to go after it</p>
                <ol className="mt-4 space-y-6">
                    {steps.map((step, index) => (
                        <li key={step.title}>
                            <p className="font-serif text-lg text-[var(--color-text)]">
                                {index + 1}. {step.title}
                            </p>
                            <p className="mt-1 text-[var(--color-text-secondary)]">{step.detail}</p>
                            {step.url && (
                                <a href={step.url} className="mt-1 inline-block underline" target="_blank" rel="noreferrer">
                                    Open it
                                </a>
                            )}
                        </li>
                    ))}
                </ol>
                <p className="mt-6 text-[var(--color-text-muted)]">
                    Every step above is yours. We will not send anything to an employer on your behalf.
                </p>
            </section>

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Ask them these before you say yes</p>
                <ul className="mt-3 space-y-2">
                    {questions.map((question) => (
                        <li key={question} className="text-[var(--color-text-secondary)]">
                            {question}
                        </li>
                    ))}
                </ul>
            </section>

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Take this to somebody</p>
                <p className="mt-2 font-serif text-xl leading-snug text-[var(--color-text)]">{conversation}</p>
            </section>
        </AppLayout>
    );
}
