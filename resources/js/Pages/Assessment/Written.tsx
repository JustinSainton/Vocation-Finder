import SupportBlock, { type Support } from '@/Components/SupportBlock';
import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ClarityCheck from '../../Components/ClarityCheck';

interface Question {
    id: string;
    question_text: string;
    category_name: string;
    category_slug: string | null;
    sort_order: number;
}

/*
 | Where the student is in the arc, not a countdown. The six movements
 | follow the category order in QuestionSeeder — what moves you, what
 | frustrates you, what absorbs you, what you choose, what has shaped
 | you, where you are headed. No numbers reach the student.
 */
const MOVEMENTS: Record<string, string> = {
    'service-orientation': 'I · What moves you',
    'problem-solving-draw': 'II · What frustrates you',
    'energy-engagement': 'III · What absorbs you',
    'values-under-pressure': 'IV · What you choose',
    'suffering-limitation': 'V · What has shaped you',
    'legacy-impact': 'VI · Where you are headed',
    'context-direction': 'VI · Where you are headed',
};

function movementFor(question: Question | undefined): string {
    if (!question) return '';
    return MOVEMENTS[question.category_slug ?? ''] ?? question.category_name ?? '';
}

interface Props {
    questions: Question[];
    assessment_id: string;
    guest_token: string | null;
}

export default function Written({ questions, assessment_id, guest_token }: Props) {
    /*
     | The clarity reading comes first, before the first question is on screen.
     | It is the baseline the whole before-and-after measure rests on, and a
     | baseline taken after somebody has started answering is a recollection.
     | Skippable, because a student who does not want to answer it should still
     | get the assessment.
     */
    const [baselineTaken, setBaselineTaken] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);
    /*
     | The synthesis pause (spec Page 4). The last answer is already saved by
     | the debounced save; this screen exists so the student arrives at the
     | results deliberately rather than being pushed into them.
     */
    const [pausing, setPausing] = useState(false);

    /*
     | Returned by the save, not computed here. The check is deterministic and
     | lives on the server, so the browser cannot be the thing that decides
     | whether somebody gets a phone number. Once shown it stays shown for the
     | rest of the assessment: it is not a validation message that clears when
     | the student edits the sentence away.
     */
    const [support, setSupport] = useState<Support | null>(null);
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
                })
                    .then((response) => (response.ok ? response.json() : null))
                    .then((data) => {
                        if (data?.support) {
                            setSupport(data.support);
                        }
                    })
                    .catch(() => {
                        // Silently fail — answer is saved locally
                    });
            }, 500);
        },
        [currentIndex, assessment_id, guest_token, question?.id]
    );

    const handleContinue = () => {
        if (isLast) {
            // Pause before the results — the synthesis page (spec Page 4).
            setPausing(true);
            return;
        }
        setCurrentIndex((i) => i + 1);
    };

    const handleFinish = () => {
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
                // Carry the token in the URL rather than relying on the
                // session. This is the link a student bookmarks, and it has to
                // still open in March when the session is long gone.
                router.visit(
                    guest_token
                        ? `/assessment/${assessment_id}/results?t=${encodeURIComponent(guest_token)}`
                        : `/assessment/${assessment_id}/results`,
                );
            });
    };

    const handleBack = () => {
        if (currentIndex > 0) {
            setCurrentIndex((i) => i - 1);
        }
    };

    if (!baselineTaken) {
        return (
            <AppLayout title="Assessment">
                <ClarityCheck
                    assessmentId={assessment_id}
                    guestToken={guest_token}
                    moment="before"
                    onAnswered={() => setBaselineTaken(true)}
                />
                <button
                    type="button"
                    onClick={() => setBaselineTaken(true)}
                    className="mt-8 font-sans text-sm text-[var(--color-text-secondary)] underline"
                >
                    Skip this
                </button>
            </AppLayout>
        );
    }

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

    if (pausing) {
        return (
            <AppLayout title="Synthesis">
                <div className="flex min-h-[70vh] flex-col justify-between">
                    <div>
                        <p className="type-eyebrow mb-6">A pause before the portrait</p>
                        <p className="text-xl leading-relaxed text-[var(--color-text)]">
                            We&rsquo;re now looking for patterns across what you shared —
                            not isolated answers, but the story they tell together.
                        </p>
                        <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                            Your reflections deserve careful attention. What comes next
                            is not a summary — it is an articulation of what already
                            lives within your responses.
                        </p>
                    </div>

                    <div className="mt-16">
                        <button
                            onClick={handleFinish}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                        >
                            Continue &rarr;
                        </button>
                        <button
                            onClick={() => setPausing(false)}
                            className="mt-4 w-full py-2 font-sans text-sm text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-text)]"
                        >
                            Return to your answers
                        </button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Assessment">
            {support && <SupportBlock support={support} />}

            {/* Category */}
            {question.category_name && (
                <p className="mb-6 type-eyebrow">
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
                <p className="mb-6 font-sans text-xs text-[var(--color-muted)]">
                    {movementFor(question)}
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
