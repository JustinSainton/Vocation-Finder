import type { CrisisSupportData } from '@/types/generated';

/**
 * What the product says instead of coaching.
 *
 * Every word of this comes from the server ({@see App\Support\CrisisCheck}),
 * fixed text that is never generated, because this is the one message that has
 * to be right every single time.
 *
 * It renders at the top of whatever the student is looking at, above the
 * questions and above the conversation. A support message underneath the thing
 * it is interrupting has not interrupted anything.
 */
export type Support = CrisisSupportData;

export default function SupportBlock({ support, surface = 'light' }: { support: Support; surface?: 'light' | 'dark' }) {
    const ink = surface === 'dark' ? 'text-[var(--color-on-dark)]' : 'text-[var(--color-text)]';
    const muted = surface === 'dark' ? 'text-[var(--color-on-dark-muted)]' : 'text-[var(--color-text-secondary)]';
    const rule = surface === 'dark' ? 'border-[var(--color-divider-dark)]' : 'border-[var(--color-divider)]';
    const accent = surface === 'dark' ? 'border-[var(--color-accent-on-dark)]' : 'border-[var(--color-accent)]';

    return (
        <section
            role="status"
            aria-live="polite"
            className={`mb-10 border-l-2 ${accent} pl-5`}
        >
            <h2 className={`font-serif text-xl leading-snug ${ink}`}>{support.heading}</h2>

            {support.body.map((line) => (
                <p key={line} className={`mt-3 ${muted}`}>
                    {line}
                </p>
            ))}

            <ul className={`mt-6 border-t ${rule}`}>
                {support.resources.map((resource) => (
                    <li key={resource.name} className={`border-b ${rule} py-4`}>
                        <p className={`font-sans text-base ${ink}`}>{resource.contact}</p>
                        <p className={`mt-1 font-sans text-sm ${muted}`}>{resource.name}</p>
                        <p className={`mt-1 font-sans text-sm ${muted}`}>{resource.note}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
