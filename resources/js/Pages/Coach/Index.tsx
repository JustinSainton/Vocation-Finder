import SupportBlock, { type Support } from '@/Components/SupportBlock';
import { router, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface CurrentAction {
    id: string;
    title: string;
    rationale: string | null;
}

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
    currentAction: CurrentAction | null;
    readiness: Readiness;
    habits: Habit[];
    invitation: Invitation | null;
}

/**
 * The coach is the front door, not a feature tab.
 *
 * The one thing the student is working on sits above the conversation rather
 * than behind a tab, because the product's claim is that they leave with one
 * concrete step — and a step you have to go looking for is a step you forget.
 */
export default function CoachIndex() {
    const { firstRun, currentAction, readiness, habits, invitation, status, support } = usePage()
        .props as unknown as Props & { status?: string; support?: Support };
    const form = useForm({ message: '' });

    /*
     * Both answers are the same size and the same weight. A big green tick
     * beside a small grey cross is a scoreboard, and a student who reads one
     * answer as the wrong one starts giving the other one whether or not it
     * is true — which is the single thing that would make this data useless.
     */
    const checkIn = (habit: Habit, happened: boolean) => {
        router.post(
            `/habits/${habit.id}/check-in`,
            { happened },
            { preserveScroll: true, preserveState: true },
        );
    };

    const send = (event: FormEvent) => {
        event.preventDefault();
        form.post('/coach/message', {
            preserveScroll: true,
            onSuccess: () => form.reset('message'),
        });
    };

    return (
        <AppLayout surface="dark">
            {/*
             | Above everything, including the step they are working on. When
             | this is on screen the careers conversation is not what is
             | happening, and the layout should not suggest otherwise.
             */}
            {support && <SupportBlock support={support} surface="dark" />}

            {currentAction ? (
                <section className="border-t border-b border-[var(--color-divider-dark)] py-6">
                    <p className="type-eyebrow">What you are doing now</p>
                    <p className="mt-2 font-serif text-xl leading-snug text-[var(--color-on-dark)]">
                        {currentAction.title}
                    </p>
                    {currentAction.rationale && (
                        <p className="mt-2 text-[var(--color-on-dark-muted)]">{currentAction.rationale}</p>
                    )}
                </section>
            ) : (
                <p className="font-serif text-xl leading-relaxed text-[var(--color-on-dark)]">{firstRun.prompt}</p>
            )}

            {/*
              * The three things the vision requires: where they stand,
              * what moves it, and how it has changed. No dial, no
              * percentage, no colour coding — a level is a word here.
              */}
            <section className="mt-12 border-t border-[var(--color-divider-dark)] pt-6">
                <p className="type-eyebrow">Where you are</p>
                <p className="mt-2 font-serif text-xl text-[var(--color-on-dark)]">{readiness.level_label}</p>
                <p className="mt-2 text-[var(--color-on-dark-muted)]">{readiness.level_description}</p>

                <p className="mt-6 type-eyebrow">What moves it</p>
                <p className="mt-2 text-[var(--color-on-dark)]">{readiness.what_moves_it}</p>

                <details className="mt-6">
                    <summary className="cursor-pointer type-eyebrow">What it is made of</summary>
                    <dl className="mt-4 space-y-4">
                        {Object.entries(readiness.factors).map(([key, factor]) => (
                            <div key={key}>
                                <dt className="text-[var(--color-on-dark)]">
                                    {factor.label} — <span className="text-[var(--color-on-dark-muted)]">{factor.standing}</span>
                                </dt>
                                <dd className="mt-1 text-sm text-[var(--color-on-dark-muted)]">{factor.move}</dd>
                            </div>
                        ))}
                    </dl>
                </details>

                {readiness.history.length > 1 && (
                    <details className="mt-4">
                        <summary className="cursor-pointer type-eyebrow">How it has changed</summary>
                        <ol className="mt-4 space-y-2 text-sm text-[var(--color-on-dark-muted)]">
                            {readiness.history.map((point, index) => (
                                <li key={index}>
                                    <span className="text-[var(--color-on-dark-muted)]">{point.on}</span> — {point.level}
                                    {point.because && ` · ${point.because}`}
                                </li>
                            ))}
                        </ol>
                    </details>
                )}
            </section>

            {/*
              * Their sentences, dated, unaltered. The system supplies the
              * observation that they said it four times; it does not supply
              * the word, and it does not tell them what it means.
              */}
            {invitation && (
                <section className="mt-12 border-t border-[var(--color-divider-dark)] pt-6">
                    <p className="type-eyebrow">Something you keep coming back to</p>
                    <p className="mt-2 font-serif text-xl leading-snug text-[var(--color-on-dark)]">
                        {invitation.prompt}
                    </p>

                    {invitation.opens_with && (
                        <ol className="mt-4 space-y-3 border-l-2 border-[var(--color-accent-on-dark)] pl-4">
                            {invitation.opens_with.in_their_words.map((said, index) => (
                                <li key={index}>
                                    <p className="type-meta">{said.said_on}</p>
                                    <p className="text-[var(--color-on-dark-muted)]">{said.content}</p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            )}

            {habits.length > 0 && (
                <section className="mt-12 border-t border-[var(--color-divider-dark)] pt-6">
                    <p className="type-eyebrow">What you keep doing</p>

                    <ul className="mt-4 space-y-8">
                        {habits.map((habit) => (
                            <li key={habit.id}>
                                <p className="font-serif text-xl leading-snug text-[var(--color-on-dark)]">
                                    {habit.title}
                                </p>
                                <p className="type-meta mt-1">
                                    {habit.cadence} · {habit.standing}
                                </p>
                                <p className="mt-2 text-[var(--color-on-dark-muted)]">{habit.meaning}</p>
                                <p className="mt-1 text-[var(--color-on-dark-muted)]">{habit.next_move}</p>

                                {habit.answered_today ? (
                                    <p className="type-meta mt-3">Answered for today.</p>
                                ) : (
                                    <div className="mt-3 flex gap-3">
                                        <button
                                            type="button"
                                            onClick={() => checkIn(habit, true)}
                                            className="border border-[var(--color-divider-dark)] px-4 py-2 font-sans text-sm text-[var(--color-on-dark)] hover:border-[var(--color-on-dark)]"
                                        >
                                            I did this
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => checkIn(habit, false)}
                                            className="border border-[var(--color-divider-dark)] px-4 py-2 font-sans text-sm text-[var(--color-on-dark)] hover:border-[var(--color-on-dark)]"
                                        >
                                            Not today
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {status && <p className="mt-8 border-l-2 border-[var(--color-accent-on-dark)] pl-4 text-[var(--color-on-dark-muted)]">{status}</p>}

            <form onSubmit={send} className="mt-10">
                <label htmlFor="message" className="type-eyebrow">
                    Say what you are actually thinking. There is no wrong answer here.
                </label>
                <textarea
                    id="message"
                    value={form.data.message}
                    onChange={(event) => form.setData('message', event.target.value)}
                    rows={4}
                    className="mt-3 w-full border border-[var(--color-divider-dark)] bg-[var(--color-surface-dark)] p-4 text-[var(--color-on-dark)] focus:border-[var(--color-accent-on-dark)] focus:outline-none"
                />
                {form.errors.message && <p className="mt-2 text-sm text-[var(--color-on-dark-muted)]">{form.errors.message}</p>}
                <button
                    type="submit"
                    disabled={form.processing || !form.data.message.trim()}
                    className="mt-4 border border-[var(--color-on-dark)] px-6 py-3 font-sans text-sm tracking-wide text-[var(--color-on-dark)] hover:bg-[var(--color-on-dark)] hover:text-[var(--color-canvas-dark)] disabled:opacity-40"
                >
                    {form.processing ? 'Thinking…' : 'Send'}
                </button>
        </form>
        </AppLayout>
    );
}
