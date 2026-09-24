import { router } from '@inertiajs/react';

export default function DemoSignIn() {
    return (
        <div className="mt-10 border-t border-[var(--color-divider)] pt-8">
            <p className="type-eyebrow text-[var(--color-muted)]">Demo mode is on</p>
            <button
                type="button"
                onClick={() => router.post('/demo-login')}
                className="mt-4 w-full border border-[var(--color-text)] bg-transparent py-4 font-sans text-sm tracking-wide text-[var(--color-text)] transition-colors hover:bg-[var(--color-surface)]"
            >
                Continue as demo &rarr;
            </button>
            <p className="mt-2 font-sans text-xs text-[var(--color-muted)]">
                Signs into the demo account and opens the assessment with its answers pre-filled.
            </p>
        </div>
    );
}
