import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

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

interface Props {
    blurb: string;
    habits: Habit[];
}

/**
 * Habits, as a place.
 *
 * Standing is a word and a next move — never a streak, a ring, a percentage
 * or a completion rate. The counts exist, and the coach uses them to tell
 * whether a habit is the wrong size; handing them to a sixteen-year-old turns
 * their week into a score, and a score is the fastest way to stop getting
 * honest answers.
 */
export default function HabitsIndex({ blurb, habits }: Props) {
    const checkIn = (habit: Habit, happened: boolean) => {
        router.post(
            `/habits/${habit.id}/check-in`,
            { happened },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AppLayout title="What you keep doing">
            <p className="type-eyebrow">What you keep doing</p>
            <p className="mt-2 font-serif text-xl leading-relaxed text-[var(--color-text)]">{blurb}</p>

            {habits.length === 0 ? (
                <p className="mt-12 border-t border-[var(--color-divider)] pt-6 text-[var(--color-text-secondary)]">
                    Nothing yet. Habits come out of the conversation — they are attached to
                    something specific you are trying to find out, never handed down as a
                    general improvement.
                </p>
            ) : (
                <ul className="mt-12 space-y-12 border-t border-[var(--color-divider)] pt-6">
                    {habits.map((habit) => (
                        <li key={habit.id}>
                            <p className="font-serif text-xl leading-snug text-[var(--color-text)]">
                                {habit.title}
                            </p>
                            <p className="type-meta mt-1">
                                {habit.cadence} · {habit.standing}
                            </p>
                            {habit.why && (
                                <p className="mt-2 text-[var(--color-text-secondary)]">{habit.why}</p>
                            )}
                            <p className="mt-2 text-[var(--color-text-secondary)]">{habit.meaning}</p>
                            <p className="mt-1 text-[var(--color-text-secondary)]">{habit.next_move}</p>

                            {/*
                              * Both answers are the same size and the same
                              * weight, here as on the coach page. A big tick
                              * beside a small cross teaches a student which
                              * answer we wanted.
                              */}
                            {habit.answered_today ? (
                                <p className="type-meta mt-4">Answered for today.</p>
                            ) : (
                                <div className="mt-4 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => checkIn(habit, true)}
                                        className="border border-[var(--color-divider)] px-4 py-2 font-sans text-sm text-[var(--color-text)] hover:border-[var(--color-text)]"
                                    >
                                        I did this
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => checkIn(habit, false)}
                                        className="border border-[var(--color-divider)] px-4 py-2 font-sans text-sm text-[var(--color-text)] hover:border-[var(--color-text)]"
                                    >
                                        Not today
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
