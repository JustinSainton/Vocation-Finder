import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface Step {
    title: string;
    detail: string;
    url: string | null;
    due_on: string | null;
    yours: boolean;
}

interface Props {
    college: {
        id: string;
        slug: string;
        name: string;
        where: string;
        kind: string;
        control: string;
        test_optional: boolean;
        application_url: string;
        programs: { name: string; url: string | null }[];
    };
    standing: { value: string; label: string; description: string };
    cost: { published: number; published_label: string; estimated: number | null; basis: string; caveat: string };
    steps: Step[];
    deadline: string | null;
    on_list: boolean;
    conversation: string;
}

const money = (value: number) => `$${value.toLocaleString()}`;

/**
 * One school, and what applying to it actually involves.
 *
 * The walkthrough is a list of things the student does. There is no button
 * anywhere on this page that submits anything to a college, and that absence
 * is the feature: "the tool will not apply for them — it gives them the
 * precise links and guides them through it step by step."
 */
export default function CollegeShow({ college, standing, cost, steps, deadline, on_list, conversation }: Props) {
    const toggleList = () => {
        const url = `/plan/colleges/${college.slug}/list`;
        on_list ? router.delete(url, { preserveScroll: true }) : router.post(url, {}, { preserveScroll: true });
    };

    return (
        <AppLayout title={college.name}>
            <p className="type-eyebrow">Plan · Colleges</p>
            <h1 className="mt-2 font-serif text-3xl leading-tight text-[var(--color-text)]">{college.name}</h1>
            <p className="mt-1 text-[var(--color-text-muted)]">
                {college.where} · {college.kind} · {college.control}
            </p>

            <button
                type="button"
                onClick={toggleList}
                className="mt-5 border border-[var(--color-divider)] px-4 py-2 text-[var(--color-text)]"
            >
                {on_list ? 'On your list — take it off' : 'Put it on your list'}
            </button>

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Where you stand</p>
                <p className="mt-2 font-serif text-xl text-[var(--color-text)]">{standing.label}</p>
                <p className="mt-1 text-[var(--color-text-secondary)]">{standing.description}</p>
            </section>

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">What it would cost you</p>
                <p className="mt-2 font-serif text-2xl text-[var(--color-text)]">
                    {cost.estimated !== null ? money(cost.estimated) : money(cost.published)} a year
                </p>
                <p className="mt-1 text-[var(--color-text-muted)]">{cost.basis}</p>
                {cost.estimated !== null && (
                    <p className="text-[var(--color-text-muted)]">
                        {cost.published_label}: {money(cost.published)}
                    </p>
                )}
                <p className="mt-3 text-[var(--color-text-secondary)]">{cost.caveat}</p>
            </section>

            {college.programs.length > 0 && (
                <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                    <p className="type-eyebrow">What they call it here</p>
                    <ul className="mt-2 space-y-1">
                        {college.programs.map((program) => (
                            <li key={program.name}>
                                {program.url ? (
                                    <a href={program.url} className="underline" target="_blank" rel="noreferrer">
                                        {program.name}
                                    </a>
                                ) : (
                                    program.name
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Applying{deadline ? ` · due ${deadline}` : ''}</p>
                <ol className="mt-4 space-y-6">
                    {steps.map((step, index) => (
                        <li key={step.title}>
                            <p className="font-serif text-lg text-[var(--color-text)]">
                                {index + 1}. {step.title}
                            </p>
                            <p className="mt-1 text-[var(--color-text-secondary)]">{step.detail}</p>
                            {step.due_on && <p className="mt-1 text-[var(--color-text-muted)]">By {step.due_on}</p>}
                            {step.url && (
                                <a href={step.url} className="mt-1 inline-block underline" target="_blank" rel="noreferrer">
                                    Open it
                                </a>
                            )}
                        </li>
                    ))}
                </ol>
                <p className="mt-6 text-[var(--color-text-muted)]">
                    Every step above is yours. We will not file anything on your behalf, and you should not let anyone
                    else either — the application is a statement you are signing.
                </p>
            </section>

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Take this to somebody</p>
                <p className="mt-2 font-serif text-xl leading-snug text-[var(--color-text)]">{conversation}</p>
            </section>
        </AppLayout>
    );
}
