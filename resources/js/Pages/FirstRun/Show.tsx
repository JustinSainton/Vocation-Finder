import { Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface FirstRun {
    step: string;
    prompt: string;
    is_terminal: boolean;
}

/**
 * What happens next, and nothing else.
 *
 * Deliberately not a progress bar. A student being shown "step 6 of 9" is
 * being told they are behind on something; the sequence ends at one action
 * they can actually take, so that is what the page says.
 */
const destinations: Record<string, { href: string; label: string } | null> = {
    assessment: { href: '/assessment', label: 'Start the questions' },
    account: { href: '/register', label: 'Save your answers' },
    results: null,
    portrait: { href: '/dashboard', label: 'Read your portrait' },
    parent_consent: null,
    checkout: { href: '/billing', label: 'Open the coach' },
    refinement: { href: '/coach', label: 'Talk to your coach' },
    first_action: { href: '/coach', label: 'Find your next step' },
    complete: { href: '/coach', label: 'Tell your coach how it went' },
};

export default function FirstRunShow() {
    const { firstRun, status } = usePage().props as unknown as {
        firstRun: FirstRun;
        status?: string;
    };
    const destination = destinations[firstRun.step];

    return (
        <AppLayout>
            <div className="mx-auto max-w-[640px] py-24">
                {status && <p className="mb-8 border-l-2 border-[var(--color-divider)] pl-4 text-[var(--color-text-secondary)]">{status}</p>}

                <p className="font-serif text-2xl leading-relaxed text-[var(--color-text)]">{firstRun.prompt}</p>

                {destination && (
                    <Link
                        href={destination.href}
                        className="mt-10 inline-block border border-[var(--color-text)] px-6 py-3 text-sm text-[var(--color-text)] hover:bg-[var(--color-text)] hover:text-[var(--color-background)]"
                    >
                        {destination.label}
                    </Link>
                )}
            </div>
        </AppLayout>
    );
}
