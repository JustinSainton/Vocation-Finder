import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface Question {
    id: string;
    question_text: string;
    category_name: string;
    sort_order: number;
}

interface Props {
    questions: Question[];
    assessment_id: string;
    guest_token: string | null;
}

export default function Written({ questions, assessment_id, guest_token }: Props) {
    const [currentIndex, setCurrentIndex] = useState(0);
    const [answers, setAnswers] = useState<Record<number, string>>({});
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const saveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const question = questions[currentIndex];
    const currentAnswer = answers[currentIndex] ?? '';
    const isLast = currentIndex === questions.length - 1;

    // Auto-focus textarea on question change
    useEffect(() => {
        textareaRef.current?.focus();
    }, [currentIndex]);

    const handleChange = useCallback(
        (value: string) => {
            setAnswers((prev) => ({ ...prev, [currentIndex]: value }));

            // Debounced save
            if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
            saveTimerRef.current = setTimeout(() => {
                const headers: Record<string, string> = {
                    'Content-Type': 'application/json',
                };
                if (guest_token) {
                    headers['X-Guest-Token'] = guest_token;
                }

                fetch(`/api/v1/assessments/${assessment_id}/answers`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        question_id: question.id,
                        response_text: value,
                    }),
                }).catch(() => {
                    // Silently fail — answer is saved locally
                });
            }, 500);
        },
        [currentIndex, assessment_id, guest_token, question?.id]
    );

    const handleContinue = () => {
        if (isLast) {
            // Complete the assessment
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
            };
            if (guest_token) {
                headers['X-Guest-Token'] = guest_token;
            }

            fetch(`/api/v1/assessments/${assessment_id}/complete`, {
                method: 'POST',
                headers,
            }).then(() => {
                router.visit(`/assessment/${assessment_id}/results`);
            });
        } else {
            setCurrentIndex((i) => i + 1);
        }
    };

    const handleBack = () => {
        if (currentIndex > 0) {
            setCurrentIndex((i) => i - 1);
        }
    };

    if (!question) {
        return (
            <AppLayout title="Assessment">
                <div className="flex min-h-[50vh] items-center justify-center">
                    <p className="text-[var(--color-text-secondary)]">
                        Preparing your questions...
                    </p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Assessment">
            {/* Category */}
            {question.category_name && (
                <p className="mb-6 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    {question.category_name}
                </p>
            )}

            {/* Question */}
            <h2 className="mb-8 font-serif text-2xl leading-snug text-[var(--color-text)]">
                {question.question_text}
            </h2>

            {/* Answer textarea */}
            <textarea
                ref={textareaRef}
                value={currentAnswer}
                onChange={(e) => handleChange(e.target.value)}
                placeholder="Take your time. Write freely."
                className="min-h-[200px] w-full resize-y border-0 bg-transparent font-serif text-lg leading-relaxed text-[var(--color-text)] outline-none placeholder:text-[var(--color-stone-400)]"
            />

            {/* Bottom area */}
            <div className="mt-12">
                <p className="mb-6 font-sans text-xs text-[var(--color-accent)]">
                    Question {currentIndex + 1} of {questions.length}
                </p>

                <button
                    onClick={handleContinue}
                    disabled={!currentAnswer.trim()}
                    className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                >
                    {isLast ? 'Finish \u2192' : 'Continue \u2192'}
                </button>

                {currentIndex > 0 && (
                    <button
                        onClick={handleBack}
                        className="mt-4 w-full py-2 font-sans text-sm text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-text)]"
                    >
                        Back
                    </button>
                )}
            </div>
        </AppLayout>
    );
}
