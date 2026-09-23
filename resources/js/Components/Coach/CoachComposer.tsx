import { FormEvent, KeyboardEvent, useEffect, useRef } from 'react';

interface Props {
    value: string;
    onChange: (value: string) => void;
    onSend: () => void;
    busy: boolean;
    starters: string[];
    onStarter: (starter: string) => void;
    showStarters: boolean;
}

/**
 * Where the student writes. Serif at reading size, because what goes in here
 * is a paragraph about their life, not a search query.
 */
export default function CoachComposer({ value, onChange, onSend, busy, starters, onStarter, showStarters }: Props) {
    const ref = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        const field = ref.current;
        if (!field) return;
        field.style.height = 'auto';
        field.style.height = `${Math.min(field.scrollHeight, 240)}px`;
    }, [value]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSend();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
            event.preventDefault();
            onSend();
        }
    };

    return (
        <div className="sticky bottom-0 -mx-6 border-t border-[var(--color-divider-dark)] bg-[var(--color-canvas-dark)] px-6 pb-6 pt-4">
            {showStarters && starters.length > 0 && (
                <div className="mb-4">
                    <p className="type-meta mb-2">Start with</p>
                    <ul className="-mx-6 flex gap-2 overflow-x-auto px-6 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                        {starters.map((starter) => (
                            <li key={starter} className="shrink-0 sm:shrink">
                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={() => onStarter(starter)}
                                    className="min-h-11 whitespace-nowrap rounded-xs border border-[var(--color-divider-dark)] px-3 py-2 text-left font-sans text-sm text-[var(--color-on-dark)] transition-colors duration-150 hover:border-[var(--color-on-dark)] disabled:opacity-40 sm:whitespace-normal"
                                >
                                    {starter}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={submit} className="flex items-end gap-3">
                <label htmlFor="coach-message" className="sr-only">
                    Say what you are actually thinking
                </label>
                <textarea
                    id="coach-message"
                    ref={ref}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onKeyDown={onKeyDown}
                    rows={1}
                    maxLength={4000}
                    placeholder="Say what you are actually thinking…"
                    className="min-h-11 flex-1 resize-none rounded-sm border border-[var(--color-divider-dark)] bg-[var(--color-surface-dark)] px-4 py-2.5 font-serif text-lg leading-[1.5] text-[var(--color-on-dark)] placeholder:text-[var(--color-on-dark-muted)] focus:border-[var(--color-accent-on-dark)] focus:outline-none"
                />
                <button
                    type="submit"
                    disabled={busy || !value.trim()}
                    className="h-11 shrink-0 bg-[var(--color-on-dark)] px-5 font-sans text-sm font-semibold text-[var(--color-canvas-dark)] transition-opacity duration-150 disabled:opacity-30"
                >
                    Send
                </button>
            </form>
            <p className="type-meta mt-2 hidden sm:block">Enter to send · Shift + Enter for a new line · Your parents never see this conversation.</p>
        </div>
    );
}
