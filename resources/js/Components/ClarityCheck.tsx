import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * One question, asked identically at both ends of the assessment.
 *
 * The wording never changes between the two readings — a question reworded in
 * between measures the rewording. Answers are words, and the comparison
 * between them happens on the server and stays there: nothing about whether
 * somebody "improved" is ever shown to them.
 */
const STANDINGS = [
    { value: 'no_idea', label: 'No idea at all' },
    { value: 'vague_sense', label: 'A vague sense' },
    { value: 'few_options', label: 'A few options in mind' },
    { value: 'know_what_to_try', label: 'I know what I want to try next' },
] as const;

interface Props {
    assessmentId: string;
    guestToken: string | null;
    moment: 'before' | 'after';
    onAnswered?: () => void;
}

export default function ClarityCheck({ assessmentId, guestToken, moment, onAnswered }: Props) {
    const [chosen, setChosen] = useState<string | null>(null);

    const answer = (standing: string) => {
        if (chosen) {
            return;
        }

        setChosen(standing);

        router.post(
            `/assessment/${assessmentId}/clarity`,
            { moment, standing, guest_token: guestToken },
            { preserveScroll: true, preserveState: true, onFinish: () => onAnswered?.() },
        );
    };

    return (
        <section>
            <p className="type-eyebrow">{moment === 'before' ? 'Before we start' : 'One last thing'}</p>
            <p className="mt-3 font-serif text-xl leading-snug text-[var(--color-text)]">
                Right now, how clear are you about what to do next?
            </p>
            <p className="mt-2 font-sans text-sm text-[var(--color-text-secondary)]">
                There is no right answer to this and it does not affect anything you are shown.
            </p>
            <div className="mt-5 flex flex-wrap gap-2">
                {STANDINGS.map((standing) => (
                    <button
                        key={standing.value}
                        type="button"
                        disabled={Boolean(chosen)}
                        aria-pressed={chosen === standing.value}
                        onClick={() => answer(standing.value)}
                        className={[
                            'border px-4 py-2 font-sans text-sm tracking-wide transition-opacity',
                            chosen === standing.value
                                ? 'border-[var(--color-text)] bg-[var(--color-text)] text-[var(--color-background)]'
                                : 'border-[var(--color-divider)] text-[var(--color-text)]',
                            chosen && chosen !== standing.value ? 'opacity-30' : '',
                        ].join(' ')}
                    >
                        {standing.label}
                    </button>
                ))}
            </div>
        </section>
    );
}
