import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Orientation() {
    const [checked, setChecked] = useState(false);

    return (
        <AppLayout title="Before We Begin">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Before we begin
                    </h1>

                    <p className="mt-8 text-lg leading-relaxed text-[var(--color-text)]">
                        This is not a test. There are no right answers, no scores, and no
                        judgment. The questions ahead are invitations to think honestly about
                        what moves you, what frustrates you, and what you find yourself
                        returning to again and again.
                    </p>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text)]">
                        Set aside roughly 30–45 minutes. This is best done in a quiet place,
                        without distractions, when you can give your full attention to the
                        process.
                    </p>

                    <p className="mt-4 font-sans text-sm text-[var(--color-accent)]">
                        ~30–45 minutes
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
                            I'm willing to answer honestly, not impressively.
                        </span>
                    </label>
                </div>

                <div className="mt-16">
                    <button
                        onClick={() => router.visit('/assessment/before')}
                        disabled={!checked}
                        className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        Continue &rarr;
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
