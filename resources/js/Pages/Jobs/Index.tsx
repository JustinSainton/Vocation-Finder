import AppLayout from '../../Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Category {
    slug: string;
    name: string;
    relevance?: number;
}

interface Job {
    id: string;
    title: string;
    company_name: string;
    location: string | null;
    is_remote: boolean;
    salary_min: number | null;
    salary_max: number | null;
    salary_currency: string;
    source_url: string | null;
    posted_at: string | null;
    match_percent?: number;
    is_saved: boolean;
    categories: Category[];
}

interface Props {
    jobs: {
        data: Job[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        pathway?: string;
        remote?: string;
        salary_min?: string;
    };
    pathways: Array<{ slug: string; name: string }>;
}

function formatSalary(min: number | null, max: number | null): string {
    const fmt = (n: number) => {
        if (n >= 1000) return `${Math.round(n / 1000)}k`;
        return `${n}`;
    };

    if (min && max) return `$${fmt(min)}\u2013$${fmt(max)}`;
    if (min) return `$${fmt(min)}+`;
    if (max) return `Up to $${fmt(max)}`;
    return '';
}

function timeAgo(dateStr: string | null): string {
    if (!dateStr) return '';
    const days = Math.floor((Date.now() - new Date(dateStr).getTime()) / 86400000);
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days}d ago`;
    if (days < 30) return `${Math.floor(days / 7)}w ago`;
    return `${Math.floor(days / 30)}mo ago`;
}

function MatchBadge({ percent }: { percent: number }) {
    const color = percent >= 80
        ? 'bg-[var(--color-text)] text-[var(--color-background)]'
        : percent >= 60
            ? 'border border-[var(--color-text)] text-[var(--color-text)]'
            : 'border border-[var(--color-accent)] text-[var(--color-text-secondary)]';

    return (
        <span className={`inline-block px-2 py-0.5 text-xs font-medium tracking-wide ${color}`}>
            {percent}% match
        </span>
    );
}

function PaginationLink({ link }: { link: { url: string | null; label: string; active: boolean } }) {
    // Strip HTML entities from label (Laravel uses &laquo; and &raquo;)
    const cleanLabel = link.label
        .replace(/&laquo;/g, '\u00AB')
        .replace(/&raquo;/g, '\u00BB')
        .replace(/<[^>]*>/g, '');

    if (!link.url) {
        return (
            <span className="px-3 py-1 text-xs text-[var(--color-accent)]">
                {cleanLabel}
            </span>
        );
    }

    return (
        <Link
            href={link.url}
            className={`px-3 py-1 text-xs ${
                link.active
                    ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text)]'
            }`}
            preserveScroll
        >
            {cleanLabel}
        </Link>
    );
}

export default function JobsIndex({ jobs, filters, pathways }: Props) {
    const { auth } = usePage<{ auth: { user: any } }>().props;
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get('/jobs', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key as keyof typeof newFilters];
        router.get('/jobs', newFilters, { preserveState: true });
    };

    const handleSave = (jobId: string, isSaved: boolean) => {
        if (isSaved) {
            router.delete(`/jobs/${jobId}/save`, { preserveScroll: true });
        } else {
            router.post(`/jobs/${jobId}/save`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Jobs">
            <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                Job Discovery
            </h1>
            <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                Jobs matched to your vocational calling.
            </p>

            {/* Search */}
            <form onSubmit={handleSearch} className="mt-6 flex gap-2">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search by title, company..."
                    className="flex-1 border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)] placeholder:text-[var(--color-accent)]"
                />
                <button
                    type="submit"
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Search
                </button>
            </form>

            {/* Filters */}
            <div className="mt-4 flex flex-wrap gap-2">
                <select
                    value={filters.pathway ?? ''}
                    onChange={(e) => handleFilter('pathway', e.target.value || undefined)}
                    className="border border-[var(--color-border)] bg-transparent px-3 py-1.5 text-xs text-[var(--color-text)]"
                >
                    <option value="">All Pathways</option>
                    {pathways.map((p) => (
                        <option key={p.slug} value={p.slug}>{p.name}</option>
                    ))}
                </select>

                <button
                    onClick={() => handleFilter('remote', filters.remote ? undefined : 'true')}
                    className={`border px-3 py-1.5 text-xs ${
                        filters.remote
                            ? 'border-[var(--color-text)] bg-[var(--color-text)] text-[var(--color-background)]'
                            : 'border-[var(--color-border)] text-[var(--color-text)]'
                    }`}
                >
                    Remote
                </button>

                <select
                    value={filters.salary_min ?? ''}
                    onChange={(e) => handleFilter('salary_min', e.target.value || undefined)}
                    className="border border-[var(--color-border)] bg-transparent px-3 py-1.5 text-xs text-[var(--color-text)]"
                >
                    <option value="">Any Salary</option>
                    <option value="30000">$30k+</option>
                    <option value="50000">$50k+</option>
                    <option value="75000">$75k+</option>
                    <option value="100000">$100k+</option>
                </select>
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Results */}
            {jobs.data.length === 0 ? (
                <div className="py-12 text-center">
                    <p className="font-serif text-lg text-[var(--color-text)]">
                        No jobs found
                    </p>
                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                        {auth.user
                            ? 'Try adjusting your filters or check back later for new listings.'
                            : 'Complete your assessment to see personalized job matches.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {jobs.data.map((job) => (
                        <Link
                            key={job.id}
                            href={`/jobs/${job.id}`}
                            className="block border border-[var(--color-border)] px-5 py-4 transition-colors hover:bg-[var(--color-surface)]"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-medium text-[var(--color-text)]">
                                            {job.title}
                                        </h3>
                                        {job.match_percent !== undefined && (
                                            <MatchBadge percent={job.match_percent} />
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-[var(--color-text-secondary)]">
                                        {job.company_name}
                                        {job.location && ` \u00B7 ${job.location}`}
                                        {job.is_remote && ' \u00B7 Remote'}
                                    </p>
                                    <div className="mt-2 flex items-center gap-3">
                                        {(job.salary_min || job.salary_max) && (
                                            <span className="text-xs text-[var(--color-text-secondary)]">
                                                {formatSalary(job.salary_min, job.salary_max)}
                                            </span>
                                        )}
                                        {job.posted_at && (
                                            <span className="text-xs text-[var(--color-accent)]">
                                                {timeAgo(job.posted_at)}
                                            </span>
                                        )}
                                        {job.categories.slice(0, 2).map((cat) => (
                                            <span
                                                key={cat.slug}
                                                className="text-xs text-[var(--color-accent)]"
                                            >
                                                {cat.name}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                                {auth.user && (
                                    <button
                                        onClick={(e) => {
                                            e.preventDefault();
                                            handleSave(job.id, job.is_saved);
                                        }}
                                        className="shrink-0 text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                                    >
                                        {job.is_saved ? 'Saved' : 'Save'}
                                    </button>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {jobs.last_page > 1 && (
                <div className="mt-8 flex justify-center gap-1">
                    {jobs.links.map((link, i) => (
                        <PaginationLink key={i} link={link} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
