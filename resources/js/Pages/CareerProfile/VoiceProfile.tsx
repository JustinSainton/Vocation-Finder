import AppLayout from '../../Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface VoiceProfileData {
    id: string;
    tone_register: string | null;
    avg_sentence_length: number | null;
    sample_count: number;
    preferred_verbs: string[] | null;
    banned_phrases: string[] | null;
    style_analysis: {
        style_summary?: string;
        vocabulary_level?: string;
        characteristic_patterns?: string[];
    } | null;
}

interface Props {
    voiceProfile: VoiceProfileData | null;
}

export default function VoiceProfile({ voiceProfile }: Props) {
    const [samples, setSamples] = useState<string[]>(['', '']);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const addSample = () => {
        if (samples.length < 10) {
            setSamples([...samples, '']);
        }
    };

    const updateSample = (index: number, value: string) => {
        const updated = [...samples];
        updated[index] = value;
        setSamples(updated);
    };

    const removeSample = (index: number) => {
        if (samples.length > 2) {
            setSamples(samples.filter((_, i) => i !== index));
        }
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);

        const validSamples = samples.filter((s) => s.trim().length >= 50);
        if (validSamples.length < 2) {
            setError('Please provide at least 2 writing samples with 50+ characters each.');
            setSubmitting(false);
            return;
        }

        try {
            const response = await fetch('/api/v1/voice-profile/samples', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ samples: validSamples }),
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('Failed to analyze samples');

            router.reload();
        } catch {
            setError('Failed to analyze writing samples. Please try again.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AppLayout title="Voice Profile">
            <Link
                href="/career-profile"
                className="font-sans text-xs tracking-wide text-[var(--color-text-secondary)] hover:text-[var(--color-text)]"
            >
                &larr; Back to Career Profile
            </Link>

            <h1 className="mt-6 font-serif text-2xl tracking-tight text-[var(--color-text)]">
                Voice Profile
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                Your voice profile captures your unique writing style. When we generate resumes
                and cover letters, they'll sound like you — not like an AI.
            </p>

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {voiceProfile ? (
                <div className="space-y-8">
                    {/* Profile Display */}
                    <div>
                        <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            Your Writing Style
                        </h2>
                        {voiceProfile.style_analysis?.style_summary && (
                            <p className="mt-3 text-sm leading-relaxed text-[var(--color-text)]">
                                {voiceProfile.style_analysis.style_summary}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-6">
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Tone
                            </p>
                            <p className="mt-1 text-sm capitalize text-[var(--color-text)]">
                                {voiceProfile.tone_register ?? 'Not analyzed'}
                            </p>
                        </div>
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Avg Sentence Length
                            </p>
                            <p className="mt-1 text-sm text-[var(--color-text)]">
                                {voiceProfile.avg_sentence_length
                                    ? `${Math.round(voiceProfile.avg_sentence_length)} words`
                                    : 'Not analyzed'}
                            </p>
                        </div>
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Vocabulary Level
                            </p>
                            <p className="mt-1 text-sm text-[var(--color-text)]">
                                {voiceProfile.style_analysis?.vocabulary_level ?? 'Not analyzed'}
                            </p>
                        </div>
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Samples Analyzed
                            </p>
                            <p className="mt-1 text-sm text-[var(--color-text)]">
                                {voiceProfile.sample_count}
                            </p>
                        </div>
                    </div>

                    {voiceProfile.preferred_verbs && voiceProfile.preferred_verbs.length > 0 && (
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Your Action Verbs
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {voiceProfile.preferred_verbs.slice(0, 15).map((verb, i) => (
                                    <span
                                        key={i}
                                        className="border border-[var(--color-border)] px-2 py-0.5 text-xs text-[var(--color-text)]"
                                    >
                                        {verb}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    {voiceProfile.banned_phrases && voiceProfile.banned_phrases.length > 0 && (
                        <div>
                            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Phrases We'll Avoid
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {voiceProfile.banned_phrases.map((phrase, i) => (
                                    <span
                                        key={i}
                                        className="border border-red-200 bg-red-50 px-2 py-0.5 text-xs text-red-700"
                                    >
                                        {phrase}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    <button
                        onClick={() => setSamples(['', ''])}
                        className="border border-[var(--color-border)] px-4 py-2 text-xs tracking-wide text-[var(--color-text)]"
                    >
                        Recalibrate with New Samples
                    </button>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            Writing Samples
                        </h2>
                        <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                            Paste 2-6 examples of your writing — cover letters you've written,
                            LinkedIn posts, professional emails, or bio text. Each should be at
                            least 50 characters.
                        </p>
                    </div>

                    {samples.map((sample, i) => (
                        <div key={i}>
                            <div className="flex items-center justify-between">
                                <label className="text-xs text-[var(--color-text-secondary)]">
                                    Sample {i + 1}
                                </label>
                                {samples.length > 2 && (
                                    <button
                                        type="button"
                                        onClick={() => removeSample(i)}
                                        className="text-xs text-red-600 underline"
                                    >
                                        Remove
                                    </button>
                                )}
                            </div>
                            <textarea
                                value={sample}
                                onChange={(e) => updateSample(i, e.target.value)}
                                rows={4}
                                placeholder="Paste a writing sample here..."
                                className="mt-1 w-full border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)] placeholder:text-[var(--color-accent)]"
                            />
                        </div>
                    ))}

                    {samples.length < 10 && (
                        <button
                            type="button"
                            onClick={addSample}
                            className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                        >
                            + Add another sample
                        </button>
                    )}

                    {error && (
                        <p className="text-xs text-red-600">{error}</p>
                    )}

                    <button
                        type="submit"
                        disabled={submitting}
                        className="bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)] disabled:opacity-50"
                    >
                        {submitting ? 'Analyzing your writing style...' : 'Analyze My Writing Style'}
                    </button>
                </form>
            )}
        </AppLayout>
    );
}
