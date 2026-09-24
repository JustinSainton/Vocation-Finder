import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import DemoSignIn from '../Components/DemoSignIn';
import AppLayout from '../Layouts/AppLayout';

interface Props {
    demo_login_available?: boolean;
}

export default function Welcome({ demo_login_available = false }: Props) {
    const { auth } = usePage<{ auth: { user: { id: string } | null } }>().props;
    const [checked, setChecked] = useState(false);

    return (
        <AppLayout title="Home">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
                    <p className="type-eyebrow mb-6">A threshold, not a test</p>
                    <h1 className="font-serif text-3xl leading-snug tracking-tight text-[var(--color-text)] md:text-4xl">
                        Most people are taught
                        <br />
                        to choose a career.
                        <br />
                        Very few are taught
                        <br />
                        to discern a calling.
                    </h1>

                    <p className="mt-8 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        This is a space for honest reflection — not a personality quiz, not
                        a career test. What follows is a guided process designed to surface
                        what you may already sense but haven&apos;t yet articulated.
                    </p>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        It requires your time and your honesty. Nothing less will do.
                    </p>

                    <p className="type-meta mt-6">
                        About 20 questions · 30–45 minutes · Your words stay yours
                    </p>

                    <div className="my-8 h-px bg-[var(--color-divider)]" />

                    <label className="flex cursor-pointer items-start gap-4">
                        <span
                            className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center border transition-colors ${
                                checked
                                    ? 'border-[var(--color-text)] bg-[var(--color-text)]'
                                    : 'border-[var(--color-text)] bg-transparent'
                            }`}
                            onClick={() => setChecked(!checked)}
                        >
                            {checked && (
                                <span className="block h-2.5 w-2.5 bg-[var(--color-background)]" />
                            )}
                        </span>
                        <span
                            className="text-lg leading-relaxed text-[var(--color-text)]"
                            onClick={() => setChecked(!checked)}
                        >
                            I&apos;m willing to answer honestly, not impressively.
                        </span>
                    </label>
                </div>

                <div className="mt-16">
                    <button
                        onClick={() => router.visit('/assessment/written')}
                        disabled={!checked}
                        className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        Begin discernment &rarr;
                    </button>

                    {!auth.user && (
                        <p className="mt-6 text-center font-sans text-sm text-[var(--color-text-secondary)]">
                            Already have an account?{' '}
                            <Link href="/login" className="link">
                                Sign in
                            </Link>
                        </p>
                    )}

                    {demo_login_available && <DemoSignIn />}
                </div>
            </div>
        </AppLayout>
    );
}
