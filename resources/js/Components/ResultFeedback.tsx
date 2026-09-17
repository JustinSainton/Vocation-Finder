import { useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * The three questions the MVP exit test is actually made of.
 *
 * The values are the enum cases in App\Enums — the server rejects anything
 * else, so the closed vocabulary lives in one place and this file is only its
 * wording. Answers are words rather than a scale on purpose: nothing numeric
 * is ever shown to a student about their own result, and a student rating
 * themselves four-out-of-five is a different act from saying "not really."
 */
const QUESTIONS = [
    { value: 'sounds_like_me', prompt: 'Does this sound like you?' },
    { value: 'useful', prompt: 'Was any of this useful?' },
    { value: 'clear_next_step', prompt: 'Do you know what to do next?' },
] as const;

const STANDINGS = [
    { value: 'not_at_all', label: 'Not at all' },
    { value: 'somewhat', label: 'Some of it' },
    { value: 'mostly', label: 'Mostly' },
    { value: 'completely', label: 'Yes, exactly' },
] as const;

type Question = (typeof QUESTIONS)[number]['value'];

interface Props {
    assessmentId: string;
    guestToken: string | null;
}

export default function ResultFeedback({ assessmentId, guestToken }: Props) {
    const [answered, setAnswered] = useState<Partial<Record<Question, string>>>({});
    const [comment, setComment] = useState('');

    /**
     * Each question posts on its own. Answering one and abandoning the rest is
     * a complete, usable data point — a submit button would throw it away.
     */
    const answer = (question: Question, standing: string) => {
        if (answered[question]) {
            return;
        }

        setAnswered((previous) => ({ ...previous, [question]: standing }));

        router.post(
            `/assessment/${assessmentId}/feedback`,
            { question, standing, comment: comment || null, guest_token: guestToken },
            { preserveScroll: true, preserveState: true },
        );
    };

    const anyAnswered = Object.keys(answered).length > 0;
    const dissented = Object.values(answered).some(
        (standing) => standing === 'not_at_all' || standing === 'somewhat',
    );

    return (
        <section className="mt-8">
            <div className="my-8 h-px bg-[var(--color-divider)]" />

            <p className="mb-6 type-eyebrow">
                Before you go
            </p>

            <div className="space-y-8">
                {QUESTIONS.map(({ value, prompt }) => (
                    <div key={value}>
                        <p className="mb-3 text-[var(--color-text)]">{prompt}</p>
                        <div className="flex flex-wrap gap-2">
                            {STANDINGS.map((standing) => {
                                const chosen = answered[value] === standing.value;
                                const settled = Boolean(answered[value]);

                                return (
                                    <button
                                        key={standing.value}
                                        type="button"
                                        disabled={settled}
                                        onClick={() => answer(value, standing.value)}
                                        aria-pressed={chosen}
                                        className={[
                                            'border px-4 py-2 font-sans text-sm tracking-wide transition-opacity',
                                            chosen
                                                ? 'border-[var(--color-text)] bg-[var(--color-text)] text-[var(--color-background)]'
                                                : 'border-[var(--color-divider)] text-[var(--color-text)]',
                                            settled && !chosen ? 'opacity-30' : '',
                                        ].join(' ')}
                                    >
                                        {standing.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            {/*
              The box appears once they have said something, and the invitation
              changes if they disagreed. A student who says "not at all" is the
              one whose words we most need, and the generic prompt is the one
              they are least likely to answer.
            */}
            {anyAnswered && (
                <div className="mt-8">
                    <label
                        htmlFor="result-feedback-comment"
                        className="mb-3 block font-sans text-sm text-[var(--color-text-secondary)]"
                    >
                        {dissented
                            ? 'What did it get wrong? Say it however you want.'
                            : 'Anything you want to add?'}
                    </label>
                    <textarea
                        id="result-feedback-comment"
                        value={comment}
                        onChange={(event) => setComment(event.target.value)}
                        rows={3}
                        maxLength={2000}
                        className="w-full border border-[var(--color-divider)] bg-transparent p-3 font-sans text-sm text-[var(--color-text)]"
                    />
                    <p className="mt-2 font-sans text-xs text-[var(--color-text-secondary)]">
                        Saved with your next answer above.
                    </p>
                </div>
            )}
        </section>
    );
}
