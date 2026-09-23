import CoachProse from './CoachProse';
import type { ThreadItem } from './types';

interface Props {
    items: ThreadItem[];
    pending: string | null;
    phase: 'idle' | 'thinking' | 'speaking';
    status: string;
    draft: string;
}

const time = (iso: string) =>
    new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

const stepWord: Record<string, string> = {
    active: 'In progress',
    completed: 'Done',
    skipped: 'Set down',
};

/**
 * The conversation.
 *
 * The coach's words sit on a tonal panel; the student's are outlined and
 * quieter, so their own sentences read as theirs — which matters in a
 * product whose brain "surfaces what they said" and nothing else.
 */
export default function CoachThread({ items, pending, phase, status, draft }: Props) {
    return (
        <ol className="space-y-6" aria-live="polite" aria-relevant="additions">
            {items.map((item, index) => {
                if (item.type === 'step') {
                    return (
                        <li key={`step-${item.id}`} className="coach-enter flex items-center gap-4 py-1">
                            <span className="h-px flex-1 bg-[var(--color-divider-dark)]" />
                            <span className="type-meta max-w-[75%] truncate text-center">
                                Step set · {item.title} · {stepWord[item.status] ?? item.status}
                            </span>
                            <span className="h-px flex-1 bg-[var(--color-divider-dark)]" />
                        </li>
                    );
                }

                const previous = items[index - 1];
                const showLabel = !previous || previous.type !== 'message' || previous.role !== item.role;

                return item.role === 'assistant' ? (
                    <li key={item.id} className="coach-enter">
                        {showLabel && <p className="type-meta mb-2">Coach · {time(item.at)}</p>}
                        <div className="rounded-md bg-[var(--color-surface-dark)] p-6 font-serif text-base leading-[1.6] text-[var(--color-on-dark)]">
                            <CoachProse text={item.content} />
                        </div>
                    </li>
                ) : (
                    <li key={item.id} className="coach-enter flex flex-col items-end">
                        {showLabel && <p className="type-meta mb-2">You · {time(item.at)}</p>}
                        <div className="max-w-[85%] whitespace-pre-wrap rounded-md border border-[var(--color-divider-dark)] px-5 py-4 font-serif text-base leading-[1.6] text-[var(--color-on-dark-muted)]">
                            {item.content}
                        </div>
                    </li>
                );
            })}

            {pending !== null && (
                <li className="coach-enter flex flex-col items-end">
                    <p className="type-meta mb-2">You · now</p>
                    <div className="max-w-[85%] whitespace-pre-wrap rounded-md border border-[var(--color-divider-dark)] px-5 py-4 font-serif text-base leading-[1.6] text-[var(--color-on-dark-muted)]">
                        {pending}
                    </div>
                </li>
            )}

            {phase === 'thinking' && (
                <li className="coach-enter" role="status">
                    <p className="type-meta mb-2">Coach</p>
                    <div className="flex items-center gap-3 rounded-md border border-[var(--color-divider-dark)] px-6 py-5">
                        <span className="coach-thinking" aria-hidden="true">
                            <span />
                            <span />
                            <span />
                        </span>
                        <span className="font-sans text-sm text-[var(--color-on-dark-muted)]">
                            {status || 'Thinking it through'}
                        </span>
                    </div>
                </li>
            )}

            {phase === 'speaking' && (
                <li className="coach-enter">
                    <p className="type-meta mb-2">Coach · now</p>
                    <div className="rounded-md bg-[var(--color-surface-dark)] p-6 font-serif text-base leading-[1.6] text-[var(--color-on-dark)]">
                        <CoachProse text={draft} />
                        <span className="coach-caret" aria-hidden="true" />
                    </div>
                </li>
            )}
        </ol>
    );
}
