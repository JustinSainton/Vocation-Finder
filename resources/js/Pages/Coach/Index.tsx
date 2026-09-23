import CoachActionCard from '@/Components/Coach/CoachActionCard';
import CoachComposer from '@/Components/Coach/CoachComposer';
import CoachThread from '@/Components/Coach/CoachThread';
import type { CoachAction, Opening, Settled, ThreadItem } from '@/Components/Coach/types';
import { useCoachStream } from '@/Components/Coach/useCoachStream';
import SupportBlock, { type Support } from '@/Components/SupportBlock';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface ReadinessFactor {
    label: string;
    standing: string;
    move: string;
}

interface Readiness {
    level_label: string;
    level_description: string;
    what_moves_it: string;
    factors: Record<string, ReadinessFactor>;
    history: { on: string; level: string; because?: string }[];
}

interface Habit {
    id: string;
    title: string;
    why: string | null;
    cadence: string;
    standing: string;
    meaning: string;
    next_move: string;
    answered_today: boolean;
}

interface Invitation {
    opens_with: {
        term: string;
        entry_count: number;
        first_said_on: string;
        in_their_words: { said_on: string; content: string }[];
    } | null;
    prompt: string;
}

interface Props {
    firstRun: { step: string; prompt: string; is_terminal: boolean };
    currentAction: CoachAction | null;
    readiness: Readiness;
    habits: Habit[];
    invitation: Invitation | null;
    thread: ThreadItem[];
    starters: string[];
    opening: Opening;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * The coach is the front door, not a feature tab.
 *
 * The conversation is the page. The one step the student is working on heads
 * it, as the only elevated card on screen, and where they stand, their
 * habits and anything they keep coming back to are one tap away rather than
 * stacked between them and the person talking to them.
 *
 * The coach speaks first. When the server says an opener is due, the page
 * asks for it on arrival, so a student who has just finished the assessment
 * never lands in front of an empty box.
 */
export default function CoachIndex() {
    const page = usePage();
    const props = page.props as unknown as Props & { status?: string; support?: Support };
    const { readiness, habits, invitation } = props;

    const [items, setItems] = useState<ThreadItem[]>(props.thread);
    const [action, setAction] = useState<CoachAction | null>(props.currentAction);
    const [starters, setStarters] = useState<string[]>(props.starters);
    const [support, setSupport] = useState<Support | null>(props.support ?? null);
    const [notice, setNotice] = useState<string | null>(props.status ?? null);
    const [message, setMessage] = useState(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('say') ?? '');
    const [pending, setPending] = useState<string | null>(null);
    const [settlingAction, setSettlingAction] = useState(false);
    const endRef = useRef<HTMLDivElement>(null);
    const opened = useRef(false);

    const onSettled = useCallback((settled: Settled) => {
        setItems(settled.items);
        setAction(settled.current_action);
        setStarters(settled.starters);
        setPending(null);
        router.reload({ only: ['readiness', 'habits', 'invitation', 'firstRun'] });
    }, []);

    const onSupport = useCallback((next: Support) => {
        setSupport(next);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const onError = useCallback((error: string) => {
        setNotice(error);
    }, []);

    const coach = useCoachStream({ onSettled, onSupport, onError });

    useEffect(() => {
        if (props.opening && !opened.current) {
            opened.current = true;
            coach.run('/coach/open');
        }
    }, [props.opening, coach]);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: items.length > 0 ? 'smooth' : 'auto', block: 'end' });
    }, [items.length, pending, coach.phase, coach.draft]);

    const send = async (text: string) => {
        const trimmed = text.trim();
        if (!trimmed || coach.busy) return;

        setNotice(null);
        setSupport(null);
        setPending(trimmed);
        setMessage('');

        const ok = await coach.run('/coach/stream', { message: trimmed });

        if (!ok) {
            setPending(null);
            setMessage((current) => current || trimmed);
        }
    };

    const settle = async (kind: 'complete' | 'skip', note: string) => {
        if (!action || settlingAction) return;
        setSettlingAction(true);

        try {
            const response = await fetch(`/coach/actions/${action.id}/${kind}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(kind === 'complete' ? { reflection: note || null } : { reason: note || null }),
            });

            if (!response.ok) throw new Error();

            const json = (await response.json()) as { current_action: CoachAction | null };
            setAction(json.current_action);
            setItems((current) =>
                current.map((item) =>
                    item.type === 'step' && item.id === action.id
                        ? { ...item, status: kind === 'complete' ? 'completed' : 'skipped' }
                        : item,
                ),
            );
            router.reload({ only: ['readiness', 'firstRun'] });
        } catch {
            setNotice('That did not save. Your step is still here — try again.');
        } finally {
            setSettlingAction(false);
        }
    };

    const checkIn = (habit: Habit, happened: boolean) => {
        router.post(`/habits/${habit.id}/check-in`, { happened }, { preserveScroll: true, preserveState: true });
    };

    const lastIsCoach = (() => {
        const last = [...items].reverse().find((item) => item.type === 'message');
        return !last || (last.type === 'message' && last.role === 'assistant');
    })();

    const unansweredHabits = habits.filter((habit) => !habit.answered_today).length;

    return (
        <AppLayout surface="dark" title="Your coach">
            {support && <SupportBlock support={support} surface="dark" />}

            <header className="mb-8 flex items-baseline justify-between gap-4">
                <div>
                    <p className="type-eyebrow">Your coach</p>
                    <h1 className="mt-2 font-serif text-[28px] leading-[1.2] tracking-[-0.25px] text-[var(--color-on-dark)]">
                        {items.length === 0 && !coach.busy ? 'It has read everything you wrote.' : 'One next step at a time.'}
                    </h1>
                </div>
                <p className="type-meta shrink-0 text-right">
                    Where you are
                    <br />
                    <span className="font-serif text-base normal-case tracking-normal text-[var(--color-on-dark)]">
                        {readiness.level_label}
                    </span>
                </p>
            </header>

            {action && (
                <div className="mb-8">
                    <CoachActionCard action={action} busy={settlingAction} onSettle={settle} />
                </div>
            )}

            <div className="mb-10 divide-y divide-[var(--color-divider-dark)] border-y border-[var(--color-divider-dark)]">
                <details className="group py-4">
                    <summary className="flex cursor-pointer list-none items-center justify-between">
                        <span className="type-eyebrow">What moves your readiness</span>
                        <span className="type-meta transition-transform duration-150 group-open:rotate-45">+</span>
                    </summary>
                    <p className="mt-3 font-serif text-[var(--color-on-dark-muted)]">{readiness.level_description}</p>
                    <p className="mt-3 font-serif text-[var(--color-on-dark)]">{readiness.what_moves_it}</p>
                    <dl className="mt-4 space-y-3">
                        {Object.entries(readiness.factors).map(([key, factor]) => (
                            <div key={key}>
                                <dt className="font-sans text-sm text-[var(--color-on-dark)]">
                                    {factor.label} — <span className="text-[var(--color-on-dark-muted)]">{factor.standing}</span>
                                </dt>
                                <dd className="mt-1 font-sans text-sm text-[var(--color-on-dark-muted)]">{factor.move}</dd>
                            </div>
                        ))}
                    </dl>
                    {readiness.history.length > 1 && (
                        <ol className="mt-4 space-y-1 font-sans text-sm text-[var(--color-on-dark-muted)]">
                            {readiness.history.map((point, index) => (
                                <li key={index}>
                                    <span className="type-meta">{point.on}</span> — {point.level}
                                    {point.because && ` · ${point.because}`}
                                </li>
                            ))}
                        </ol>
                    )}
                </details>

                {habits.length > 0 && (
                    <details className="group py-4" open={unansweredHabits > 0}>
                        <summary className="flex cursor-pointer list-none items-center justify-between">
                            <span className="type-eyebrow">What you keep doing · {habits.length}</span>
                            <span className="type-meta transition-transform duration-150 group-open:rotate-45">+</span>
                        </summary>
                        <ul className="mt-2">
                            {habits.map((habit) => (
                                <li key={habit.id} className="border-b border-[var(--color-divider-dark)] py-4 last:border-b-0">
                                    <p className="font-serif text-lg leading-snug text-[var(--color-on-dark)]">{habit.title}</p>
                                    <p className="type-meta mt-1">
                                        {habit.cadence} · {habit.standing}
                                    </p>
                                    <p className="mt-2 font-sans text-sm text-[var(--color-on-dark-muted)]">{habit.next_move}</p>
                                    {habit.answered_today ? (
                                        <p className="type-meta mt-3">Answered for today.</p>
                                    ) : (
                                        <div className="mt-3 flex gap-3">
                                            {[true, false].map((happened) => (
                                                <button
                                                    key={String(happened)}
                                                    type="button"
                                                    onClick={() => checkIn(habit, happened)}
                                                    className="h-11 min-w-[120px] border border-[var(--color-divider-dark)] px-4 font-sans text-sm text-[var(--color-on-dark)] transition-colors duration-150 hover:border-[var(--color-on-dark)]"
                                                >
                                                    {happened ? 'I did this' : 'Not today'}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </details>
                )}

                {invitation && (
                    <details className="group py-4" open>
                        <summary className="flex cursor-pointer list-none items-center justify-between">
                            <span className="type-eyebrow">Something you keep coming back to</span>
                            <span className="type-meta transition-transform duration-150 group-open:rotate-45">+</span>
                        </summary>
                        <p className="mt-3 font-serif text-lg leading-snug text-[var(--color-on-dark)]">{invitation.prompt}</p>
                        {invitation.opens_with && (
                            <ol className="mt-4 space-y-3 border-l-2 border-[var(--color-accent-on-dark)] pl-4">
                                {invitation.opens_with.in_their_words.map((said, index) => (
                                    <li key={index}>
                                        <p className="type-meta">{said.said_on}</p>
                                        <p className="font-serif text-[var(--color-on-dark-muted)]">{said.content}</p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </details>
                )}
            </div>

            <CoachThread items={items} pending={pending} phase={coach.phase} status={coach.status} draft={coach.draft} />

            {notice && (
                <p role="alert" className="mt-6 border-l-2 border-[var(--color-accent-on-dark)] pl-4 font-sans text-sm text-[var(--color-on-dark-muted)]">
                    {notice}
                    {props.opening && items.length === 0 && !coach.busy && (
                        <button type="button" onClick={() => coach.run('/coach/open')} className="link ml-2">
                            Try again
                        </button>
                    )}
                </p>
            )}

            <div ref={endRef} className="h-8" />

            <CoachComposer
                value={message}
                onChange={setMessage}
                onSend={() => send(message)}
                busy={coach.busy}
                starters={starters}
                onStarter={(starter) => send(starter)}
                showStarters={!coach.busy && lastIsCoach && !message}
            />
        </AppLayout>
    );
}
