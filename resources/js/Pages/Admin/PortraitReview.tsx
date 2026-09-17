import { router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

interface Props {
    coverage: { portraits: number; reviewed: number; failures: Record<string, number> };
    dimensions: { value: string; label: string; question: string }[];
    standings: { value: string; label: string }[];
    portrait: {
        id: string;
        opening_synthesis: string | null;
        vocational_orientation: string | null;
        primary_pathways: unknown;
        next_steps: string | null;
        confidence_level: string | null;
        prompt_version: string | null;
        answers: { question: string | null; response: string | null }[];
    } | null;
}

/**
 * The review bench.
 *
 * The reviewer is handed the next portrait; there is no way to browse for one.
 * The student's own answers sit beside it because most of the nine questions
 * cannot be answered from the portrait alone.
 */
export default function AdminPortraitReview({ coverage, dimensions, standings, portrait }: Props) {
    const [answered, setAnswered] = useState<Record<string, string>>({});
    const [note, setNote] = useState('');

    const answer = (dimension: string, standing: string) => {
        if (!portrait || answered[dimension]) {
            return;
        }

        setAnswered((previous) => ({ ...previous, [dimension]: standing }));

        router.post(
            `/admin/reviews/${portrait.id}`,
            { dimension, standing, note: note || null },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AdminLayout title="Review">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-stone-900">Read the next one</h1>
                <p className="mt-1 text-sm text-stone-500">
                    {coverage.reviewed} of {coverage.portraits} portraits have been read by somebody. You do not choose
                    which one comes next — the ones students objected to come first, then the oldest unread.
                </p>
            </div>

            {!portrait && <p className="text-stone-500">Nothing left in your queue.</p>}

            {portrait && (
                <div className="grid gap-8 lg:grid-cols-2">
                    <section>
                        <h2 className="text-xs tracking-widest text-stone-500 uppercase">What they wrote</h2>
                        <dl className="mt-4 space-y-4">
                            {portrait.answers.map((answer, index) => (
                                <div key={index}>
                                    <dt className="text-sm text-stone-500">{answer.question}</dt>
                                    <dd className="mt-1 text-stone-900">{answer.response}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section>
                        <h2 className="text-xs tracking-widest text-stone-500 uppercase">
                            What the engine said · {portrait.confidence_level ?? 'no confidence recorded'} ·{' '}
                            {portrait.prompt_version ?? 'no prompt version'}
                        </h2>
                        <div className="mt-4 space-y-4 text-stone-900">
                            <p>{portrait.opening_synthesis}</p>
                            <p>{portrait.vocational_orientation}</p>
                            <p>{portrait.next_steps}</p>
                        </div>

                        <div className="mt-8 space-y-6">
                            {dimensions.map((dimension) => (
                                <div key={dimension.value}>
                                    <p className="text-sm font-medium text-stone-900">{dimension.label}</p>
                                    <p className="mt-1 text-sm text-stone-500">{dimension.question}</p>
                                    <div className="mt-2 flex gap-2">
                                        {standings.map((standing) => (
                                            <button
                                                key={standing.value}
                                                type="button"
                                                disabled={Boolean(answered[dimension.value])}
                                                onClick={() => answer(dimension.value, standing.value)}
                                                className={[
                                                    'border px-3 py-1 text-sm',
                                                    answered[dimension.value] === standing.value
                                                        ? 'border-stone-900 bg-stone-900 text-white'
                                                        : 'border-stone-300 text-stone-700',
                                                    answered[dimension.value] &&
                                                    answered[dimension.value] !== standing.value
                                                        ? 'opacity-30'
                                                        : '',
                                                ].join(' ')}
                                            >
                                                {standing.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={3}
                            maxLength={2000}
                            placeholder="What went wrong, in your words. Saved with your next answer."
                            className="mt-6 w-full border border-stone-300 p-3 text-sm"
                        />
                    </section>
                </div>
            )}
        </AdminLayout>
    );
}
