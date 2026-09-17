import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface Result {
    id: string;
    title: string;
    company_name: string;
    location: string | null;
    is_remote: boolean;
    kind: string;
    minimum_age: number | null;
    supervised: boolean | null;
    pay: string;
}

interface Props {
    filters: Record<string, string | undefined>;
    results: Result[];
}

/**
 * Work that has been through the vetting pass.
 *
 * Nothing on this page carries a caution, because nothing that would need one
 * reaches it — a listing either passed vetting and is here, or did not and is
 * absent. A page of warnings teaches students to read past warnings.
 *
 * Apprenticeships sit in the same list as jobs, unhighlighted. Separating them
 * into their own tab would be an argument about which is the real option.
 */
export default function WorkIndex({ filters, results }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (next: Record<string, string>) => {
        router.get('/plan/work', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout title="Work">
            <p className="type-eyebrow">Plan · Work</p>
            <h1 className="mt-2 font-serif text-3xl leading-tight text-[var(--color-text)]">
                Work worth your time, checked before you see it
            </h1>
            <p className="mt-3 text-[var(--color-text-secondary)]">
                Every posting here has been through a check for the things that make a job posting a scam, and every one
                of them says how old you have to be. What is missing from this list is missing on purpose.
            </p>

            <section className="mt-8 flex flex-wrap gap-3 border-t border-b border-[var(--color-divider)] py-4">
                <input
                    aria-label="Search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onBlur={() => apply({ search })}
                    placeholder="Search"
                    className="w-48 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                />
                <select
                    aria-label="Kind of work"
                    value={filters.kind ?? ''}
                    onChange={(event) => apply({ kind: event.target.value })}
                    className="border border-[var(--color-divider)] bg-transparent px-2 py-1"
                >
                    <option value="">Any kind of work</option>
                    <option value="job">Jobs</option>
                    <option value="apprenticeship">Apprenticeships</option>
                    <option value="internship">Internships</option>
                </select>
            </section>

            <ul className="mt-8 space-y-8">
                {results.map((result) => (
                    <li key={result.id} className="border-b border-[var(--color-divider)] pb-8">
                        <Link href={`/plan/work/${result.id}`} className="font-serif text-xl text-[var(--color-text)] underline">
                            {result.title}
                        </Link>
                        <p className="mt-1 text-[var(--color-text-muted)]">
                            {result.company_name}
                            {result.location ? ` · ${result.location}` : ''}
                            {result.is_remote ? ' · Remote' : ''} · {result.kind}
                        </p>
                        <p className="mt-2 text-[var(--color-text)]">{result.pay}</p>
                        {result.minimum_age !== null && (
                            <p className="text-[var(--color-text-muted)]">You must be {result.minimum_age} or older.</p>
                        )}
                    </li>
                ))}
            </ul>

            {results.length === 0 && (
                <p className="mt-8 text-[var(--color-text-secondary)]">
                    Nothing here yet that you are old enough to do and that passed the check. That is not a statement
                    about you — it is a statement about what has been posted this week.
                </p>
            )}
        </AppLayout>
    );
}
