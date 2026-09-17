import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface Entry {
    id: string;
    said_on: string;
    context: string | null;
    in_their_words: string;
    source: string;
}

interface Props {
    blurb: string;
    topic: string;
    total: number;
    entries: Entry[];
}

/**
 * The brain, read.
 *
 * Every visible string below is either the student's own sentence, a date, or
 * a label the system owns. There is no summary, no theme, no "it sounds like
 * you care about…" — the moment this page paraphrases, the thing being handed
 * back is ours rather than theirs.
 */
export default function BrainIndex({ blurb, topic, total, entries }: Props) {
    const [query, setQuery] = useState(topic);

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get('/brain', query.trim() ? { topic: query.trim() } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="Your words" surface="dark">
            <p className="type-eyebrow">Your words</p>
            <p className="mt-2 font-serif text-xl leading-relaxed text-[var(--color-on-dark)]">{blurb}</p>

            <form onSubmit={search} className="mt-8">
                <label htmlFor="topic" className="type-eyebrow">
                    Look for something you said
                </label>
                <div className="mt-3 flex gap-3">
                    <input
                        id="topic"
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="teaching, my dad, money…"
                        className="flex-1 border-0 border-b border-[var(--color-divider-dark)] bg-transparent px-0 py-2 font-serif text-lg text-[var(--color-on-dark)] outline-none placeholder:text-[var(--color-on-dark-muted)] focus:border-[var(--color-accent-on-dark)]"
                    />
                    <button
                        type="submit"
                        className="border border-[var(--color-divider-dark)] px-5 py-2 font-sans text-sm text-[var(--color-on-dark)] hover:border-[var(--color-on-dark)]"
                    >
                        Find it
                    </button>
                </div>
            </form>

            {entries.length === 0 ? (
                <p className="mt-12 border-t border-[var(--color-divider-dark)] pt-6 text-[var(--color-on-dark-muted)]">
                    {total === 0
                        ? 'There is nothing here yet. It fills up as you talk, and it is yours when it does.'
                        : 'Nothing here matches that. Try a word you would actually have used.'}
                </p>
            ) : (
                <ol className="mt-12 space-y-8 border-t border-[var(--color-divider-dark)] pt-6">
                    {entries.map((entry) => (
                        <li key={entry.id}>
                            <p className="type-meta">
                                {entry.said_on} · {entry.source}
                                {entry.context ? ` · ${entry.context}` : ''}
                            </p>
                            <p className="mt-2 border-l-2 border-[var(--color-accent-on-dark)] pl-4 font-serif text-lg leading-relaxed whitespace-pre-line text-[var(--color-on-dark)]">
                                {entry.in_their_words}
                            </p>
                        </li>
                    ))}
                </ol>
            )}

            {/*
              * Always here, on every visit, regardless of what anybody is
              * paying. The promise is that this can be taken away from us,
              * not that it can be taken away from them.
              */}
            <p className="mt-12 border-t border-[var(--color-divider-dark)] pt-6">
                <a href="/brain/export" className="link">
                    Download all of it
                </a>
            </p>
        </AppLayout>
    );
}
