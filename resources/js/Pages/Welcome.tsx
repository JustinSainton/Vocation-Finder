import AppLayout from '../Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function Welcome() {
    return (
        <AppLayout title="Home">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
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
                        what you may already sense but haven't yet articulated.
                    </p>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        It requires your time and your honesty. Nothing less will do.
                    </p>
                </div>

                <div className="mt-16">
                    <button
                        onClick={() => router.visit('/assessment')}
                        className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                    >
                        Begin discernment &rarr;
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
