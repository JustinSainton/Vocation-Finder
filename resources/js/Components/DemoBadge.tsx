import { usePage } from '@inertiajs/react';

export interface DemoMode {
    persona: string;
    clarity: { before: string; after: string };
}

/**
 * Null for everyone except a demo account in an environment with demo mode
 * switched on. The server decides; the browser only reads the answer.
 */
export function useDemoMode(): DemoMode | null {
    return usePage<{ demo?: DemoMode | null }>().props.demo ?? null;
}

export default function DemoBadge() {
    const demo = useDemoMode();

    if (!demo) {
        return null;
    }

    return (
        <p
            role="status"
            className="type-eyebrow mb-8 inline-flex items-center gap-2 rounded-full border border-[var(--color-divider)] px-3 py-1 text-[var(--color-muted)]"
        >
            <span className="text-[var(--color-accent)]">Demo mode</span>
            <span aria-hidden>·</span>
            answers pre-filled as {demo.persona}
        </p>
    );
}
