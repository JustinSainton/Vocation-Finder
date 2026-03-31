import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Before() {
    const [clarityScore, setClarityScore] = useState<number | null>(null);
    const [readinessScore, setReadinessScore] = useState<number | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const canSubmit = clarityScore !== null && readinessScore !== null && !submitting;

    const handleSubmit = () => {
        if (!canSubmit) return;
        setSubmitting(true);
        router.post('/assessment/begin', {
            clarity_score: clarityScore,
            action_score: readinessScore,
        });
    };

    return (
        <AppLayout title="Before You Begin">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Before You Begin
                    </h1>

                    <p className="mt-4 font-sans text-sm text-[var(--color-accent)]">
                        This will take 3–5 minutes.
                    </p>
                    <p className="mt-1 font-sans text-sm text-[var(--color-text-secondary)]">
                        Answer honestly — this is for your clarity.
                    </p>

                    <div className="my-8 h-px bg-[var(--color-divider)]" />

                    {/* Question 1 */}
                    <div className="mb-10">
                        <p className="mb-4 text-lg leading-relaxed text-[var(--color-text)]">
                            Before taking this assessment, how clear are you about your vocational direction?
                        </p>
                        <p className="mb-4 font-sans text-xs text-[var(--color-text-secondary)]">
                            1 = no clarity &nbsp;·&nbsp; 10 = extremely clear
                        </p>
                        <ScoreSelector value={clarityScore} onChange={setClarityScore} />
                    </div>

                    {/* Question 2 */}
                    <div className="mb-10">
                        <p className="mb-4 text-lg leading-relaxed text-[var(--color-text)]">
                            Before taking this assessment, how ready do you feel to make a real decision about your future (career, education, or calling)?
                        </p>
                        <p className="mb-4 font-sans text-xs text-[var(--color-text-secondary)]">
                            1 = not ready at all &nbsp;·&nbsp; 10 = fully ready
                        </p>
                        <ScoreSelector value={readinessScore} onChange={setReadinessScore} />
                    </div>
                </div>

                <div className="mt-8">
                    <button
                        onClick={handleSubmit}
                        disabled={!canSubmit}
                        className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        Begin Assessment &rarr;
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}

function ScoreSelector({
    value,
    onChange,
}: {
    value: number | null;
    onChange: (v: number) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
                <button
                    key={n}
                    type="button"
                    onClick={() => onChange(n)}
                    className={`flex h-10 w-10 items-center justify-center border font-sans text-sm transition-colors ${
                        value === n
                            ? 'border-[var(--color-text)] bg-[var(--color-text)] text-[var(--color-background)]'
                            : 'border-[var(--color-divider)] bg-transparent text-[var(--color-text)] hover:border-[var(--color-text)]'
                    }`}
                >
                    {n}
                </button>
            ))}
        </div>
    );
}
