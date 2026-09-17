import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface ArtifactView {
    id: string;
    kind: string;
    title: string;
    note: string | null;
    added_on: string;
    is_file: boolean;
    original_name: string | null;
    link_url: string | null;
}

interface Props {
    blurb: string;
    kinds: { value: string; label: string }[];
    artifacts: ArtifactView[];
    max_kilobytes: number;
}

/**
 * The locker.
 *
 * There is nothing on this page that offers to write, rewrite, score or
 * improve anything. It stores what the student made and hands it back — the
 * tool never does the student's work for them, and an "improve my essay"
 * button here would undo that in one click.
 */
export default function LockerIndex({ blurb, kinds, artifacts, max_kilobytes }: Props) {
    const [mode, setMode] = useState<'file' | 'link'>('file');

    const form = useForm<{
        kind: string;
        title: string;
        note: string;
        file: File | null;
        link_url: string;
    }>({
        kind: kinds[0]?.value ?? 'other',
        title: '',
        note: '',
        file: null,
        link_url: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            file: mode === 'file' ? data.file : null,
            link_url: mode === 'link' ? data.link_url : '',
        }));
        form.post('/locker', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset('title', 'note', 'file', 'link_url'),
        });
    };

    const remove = (artifact: ArtifactView) => {
        router.delete(`/locker/${artifact.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout title="Locker">
            <p className="type-eyebrow">Locker</p>
            <p className="mt-2 font-serif text-xl leading-relaxed text-[var(--color-text)]">{blurb}</p>

            <form onSubmit={submit} className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <p className="type-eyebrow">Put something in</p>

                <label htmlFor="title" className="mt-4 block text-[var(--color-text-secondary)]">
                    What is it?
                </label>
                <input
                    id="title"
                    value={form.data.title}
                    onChange={(event) => form.setData('title', event.target.value)}
                    className="mt-2 w-full border-0 border-b border-[var(--color-divider)] bg-transparent px-0 py-2 font-serif text-lg text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                />
                {form.errors.title && <p className="mt-1 text-sm text-[var(--color-muted)]">{form.errors.title}</p>}

                <label htmlFor="kind" className="mt-6 block text-[var(--color-text-secondary)]">
                    What sort of thing
                </label>
                <select
                    id="kind"
                    value={form.data.kind}
                    onChange={(event) => form.setData('kind', event.target.value)}
                    className="mt-2 w-full border border-[var(--color-divider)] bg-transparent px-3 py-2 font-sans text-sm text-[var(--color-text)]"
                >
                    {kinds.map((kind) => (
                        <option key={kind.value} value={kind.value}>
                            {kind.label}
                        </option>
                    ))}
                </select>

                <div className="mt-6 flex gap-6">
                    {(['file', 'link'] as const).map((option) => (
                        <label key={option} className="flex items-center gap-2 text-[var(--color-text-secondary)]">
                            <input
                                type="radio"
                                name="mode"
                                checked={mode === option}
                                onChange={() => setMode(option)}
                            />
                            {option === 'file' ? 'Upload a file' : 'Link to it'}
                        </label>
                    ))}
                </div>

                {mode === 'file' ? (
                    <>
                        <input
                            type="file"
                            onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            className="mt-4 block w-full font-sans text-sm text-[var(--color-text-secondary)]"
                        />
                        <p className="type-meta mt-2">
                            PDF, Word, text or an image, up to {Math.round(max_kilobytes / 1024)} MB.
                        </p>
                        {form.errors.file && <p className="mt-1 text-sm text-[var(--color-muted)]">{form.errors.file}</p>}
                    </>
                ) : (
                    <>
                        <input
                            type="url"
                            value={form.data.link_url}
                            onChange={(event) => form.setData('link_url', event.target.value)}
                            placeholder="https://"
                            className="mt-4 w-full border-0 border-b border-[var(--color-divider)] bg-transparent px-0 py-2 font-serif text-lg text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        />
                        {form.errors.link_url && (
                            <p className="mt-1 text-sm text-[var(--color-muted)]">{form.errors.link_url}</p>
                        )}
                    </>
                )}

                <label htmlFor="note" className="mt-6 block text-[var(--color-text-secondary)]">
                    Anything you want to remember about it (optional)
                </label>
                <textarea
                    id="note"
                    rows={2}
                    value={form.data.note}
                    onChange={(event) => form.setData('note', event.target.value)}
                    className="mt-2 w-full border border-[var(--color-divider)] bg-transparent p-3 text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                />

                <button
                    type="submit"
                    disabled={form.processing || !form.data.title.trim()}
                    className="action-secondary mt-6 disabled:opacity-40"
                >
                    {form.processing ? 'Saving…' : 'Keep it'}
                </button>
            </form>

            {artifacts.length === 0 ? (
                <p className="mt-12 border-t border-[var(--color-divider)] pt-6 text-[var(--color-text-secondary)]">
                    Nothing in here yet. This is for the things you make — the essay, the résumé, the
                    thing you built that you would want someone to see.
                </p>
            ) : (
                <ul className="mt-12 space-y-8 border-t border-[var(--color-divider)] pt-6">
                    {artifacts.map((artifact) => (
                        <li key={artifact.id}>
                            <p className="font-serif text-lg leading-snug text-[var(--color-text)]">{artifact.title}</p>
                            <p className="type-meta mt-1">
                                {artifact.added_on} · {artifact.kind}
                                {artifact.original_name ? ` · ${artifact.original_name}` : ''}
                            </p>
                            {artifact.note && (
                                <p className="mt-2 text-[var(--color-text-secondary)]">{artifact.note}</p>
                            )}

                            <div className="mt-3 flex flex-wrap gap-4">
                                {artifact.is_file ? (
                                    <a href={`/locker/${artifact.id}/download`} className="link">
                                        Download
                                    </a>
                                ) : (
                                    <a
                                        href={artifact.link_url ?? '#'}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="link"
                                    >
                                        Open
                                    </a>
                                )}
                                <button
                                    type="button"
                                    onClick={() => remove(artifact)}
                                    className="font-sans text-sm text-[var(--color-muted)] underline underline-offset-4"
                                >
                                    Take it out
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
