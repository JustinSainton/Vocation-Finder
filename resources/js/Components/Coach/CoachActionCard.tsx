import { useState } from 'react';
import type { CoachAction } from './types';

interface Props {
    action: CoachAction;
    busy: boolean;
    onSettle: (kind: 'complete' | 'skip', note: string) => void;
}

/**
 * The one step. DESIGN.md's single most important element: elevated, bordered
 * in the accent, and never more than one on screen.
 *
 * "Done" asks how it went before it closes, because the reflection is the
 * most valuable thing the brain holds — the student describing something that
 * happened rather than something they intend.
 */
export default function CoachActionCard({ action, busy, onSettle }: Props) {
    const [settling, setSettling] = useState<'complete' | 'skip' | null>(null);
    const [note, setNote] = useState('');

    return (
        <section
            aria-label="Your one step"
            className="rounded-md border border-[var(--color-accent-on-dark)] bg-[var(--color-surface-dark-elevated)] p-6"
        >
            <p className="type-eyebrow">Your one step</p>
            <p className="mt-3 font-sans text-lg font-semibold leading-snug text-[var(--color-on-dark)]">{action.title}</p>
            {action.rationale && (
                <p className="mt-2 font-serif leading-[1.6] text-[var(--color-on-dark-muted)]">{action.rationale}</p>
            )}

            {settling ? (
                <form
                    className="mt-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSettle(settling, note.trim());
                    }}
                >
                    <label htmlFor="step-note" className="type-meta">
                        {settling === 'complete' ? 'How did it go? In your own words — optional.' : 'Why set it down? Optional, and there is no wrong reason.'}
                    </label>
                    <textarea
                        id="step-note"
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        rows={3}
                        className="mt-2 w-full rounded-sm border border-[var(--color-divider-dark)] bg-[var(--color-canvas-dark)] p-4 font-serif text-[var(--color-on-dark)] focus:border-[var(--color-accent-on-dark)] focus:outline-none"
                    />
                    <div className="mt-3 flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={busy}
                            className="h-11 bg-[var(--color-on-dark)] px-6 font-sans text-sm font-semibold text-[var(--color-canvas-dark)] transition-opacity duration-150 disabled:opacity-40"
                        >
                            {settling === 'complete' ? 'Mark it done' : 'Set it down'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setSettling(null)}
                            className="h-11 border border-[var(--color-divider-dark)] px-6 font-sans text-sm text-[var(--color-on-dark)] transition-colors duration-150 hover:border-[var(--color-on-dark)]"
                        >
                            Not yet
                        </button>
                    </div>
                </form>
            ) : (
                <div className="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => setSettling('complete')}
                        className="h-11 bg-[var(--color-on-dark)] px-6 font-sans text-sm font-semibold text-[var(--color-canvas-dark)]"
                    >
                        I did this
                    </button>
                    <button
                        type="button"
                        onClick={() => setSettling('skip')}
                        className="h-11 border border-[var(--color-divider-dark)] px-6 font-sans text-sm text-[var(--color-on-dark)] transition-colors duration-150 hover:border-[var(--color-on-dark)]"
                    >
                        Set it down
                    </button>
                </div>
            )}
        </section>
    );
}
