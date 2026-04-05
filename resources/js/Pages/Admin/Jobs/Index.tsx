import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Category {
    slug: string;
    name: string;
}

interface Job {
    id: string;
    title: string;
    company_name: string;
    location: string | null;
    is_remote: boolean;
    salary_min: number | null;
    salary_max: number | null;
    source: string;
    source_url: string | null;
    soc_code: string | null;
    classification_status: string;
    posted_at: string | null;
    created_at: string;
    vocational_categories: Array<{ slug: string; name: string; pivot: { relevance_score: number } }>;
}

interface Stats {
    total: number;
    active: number;
    classified: number;
    pending: number;
    sources: Record<string, number>;
}

interface Props {
    jobs: {
        data: Job[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
    stats: Stats;
    filters: { search?: string; source?: string; classification_status?: string };
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatSalary(min: number | null, max: number | null): string {
    const fmt = (n: number) => `$${Math.round(n / 1000)}k`;
    if (min && max) return `${fmt(min)}\u2013${fmt(max)}`;
    if (min) return `${fmt(min)}+`;
    if (max) return `Up to ${fmt(max)}`;
    return '';
}

export default function AdminJobsIndex({ jobs, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/jobs', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key as keyof typeof newFilters];
        router.get('/admin/jobs', newFilters, { preserveState: true });
    };

    return (
        <AdminLayout title="Job Listings">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Job Listings</h1>

            {/* Stats */}
            <div className="mt-6 grid grid-cols-4 gap-4">
                <div className="border border-[var(--color-border)] p-4">
                    <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Total</p>
                    <p className="mt-1 text-2xl font-light text-[var(--color-text)]">{stats.total}</p>
                </div>
                <div className="border border-[var(--color-border)] p-4">
                    <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Active</p>
                    <p className="mt-1 text-2xl font-light text-[var(--color-text)]">{stats.active}</p>
                </div>
                <div className="border border-[var(--color-border)] p-4">
                    <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Classified</p>
                    <p className="mt-1 text-2xl font-light text-[var(--color-text)]">{stats.classified}</p>
                </div>
                <div className="border border-[var(--color-border)] p-4">
                    <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Pending</p>
                    <p className="mt-1 text-2xl font-light text-[var(--color-text)]">{stats.pending}</p>
                </div>
            </div>

            {/* Sources */}
            {Object.keys(stats.sources).length > 0 && (
                <div className="mt-4 flex gap-3">
                    {Object.entries(stats.sources).map(([source, count]) => (
                        <span key={source} className="border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-text)]">
                            {source}: {count}
                        </span>
                    ))}
                </div>
            )}

            {/* Search + Filters */}
            <div className="mt-6 flex gap-3">
                <form onSubmit={handleSearch} className="flex flex-1 gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by title or company..."
                        className="flex-1 border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                    />
                    <button type="submit" className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]">
                        Search
                    </button>
                </form>
                <select
                    value={filters.source ?? ''}
                    onChange={(e) => handleFilter('source', e.target.value || undefined)}
                    className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-xs text-[var(--color-text)]"
                >
                    <option value="">All Sources</option>
                    <option value="adzuna">Adzuna</option>
                    <option value="jsearch">JSearch</option>
                    <option value="muse">The Muse</option>
                </select>
                <select
                    value={filters.classification_status ?? ''}
                    onChange={(e) => handleFilter('classification_status', e.target.value || undefined)}
                    className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-xs text-[var(--color-text)]"
                >
                    <option value="">All Status</option>
                    <option value="classified">Classified</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            {/* Job Table */}
            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th className="pb-2">Title / Company</th>
                        <th className="pb-2">Location</th>
                        <th className="pb-2">Salary</th>
                        <th className="pb-2">Source</th>
                        <th className="pb-2">Categories</th>
                        <th className="pb-2">Status</th>
                        <th className="pb-2">Posted</th>
                    </tr>
                </thead>
                <tbody>
                    {jobs.data.length === 0 ? (
                        <tr>
                            <td colSpan={7} className="py-8 text-center text-[var(--color-text-secondary)]">
                                No job listings found. Run <code className="bg-gray-100 px-1">php artisan jobs:ingest --source=adzuna --classify</code> to import jobs.
                            </td>
                        </tr>
                    ) : (
                        jobs.data.map((job) => (
                            <tr key={job.id} className="border-b border-[var(--color-border)]">
                                <td className="py-3">
                                    <p className="text-sm text-[var(--color-text)]">{job.title}</p>
                                    <p className="text-xs text-[var(--color-text-secondary)]">{job.company_name}</p>
                                </td>
                                <td className="py-3 text-xs text-[var(--color-text-secondary)]">
                                    {job.location ?? ''}
                                    {job.is_remote && <span className="ml-1 text-green-600">Remote</span>}
                                </td>
                                <td className="py-3 text-xs text-[var(--color-text-secondary)]">
                                    {formatSalary(job.salary_min, job.salary_max)}
                                </td>
                                <td className="py-3">
                                    <span className="text-xs capitalize text-[var(--color-text-secondary)]">{job.source}</span>
                                </td>
                                <td className="py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {job.vocational_categories.slice(0, 2).map((cat) => (
                                            <span key={cat.slug} className="border border-[var(--color-border)] px-1.5 py-0.5 text-[10px] text-[var(--color-text-secondary)]">
                                                {cat.name}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="py-3">
                                    <span className={`text-xs ${
                                        job.classification_status === 'classified' ? 'text-green-600' :
                                        job.classification_status === 'failed' ? 'text-red-600' :
                                        'text-[var(--color-accent)]'
                                    }`}>
                                        {job.classification_status}
                                    </span>
                                </td>
                                <td className="py-3 text-xs text-[var(--color-accent)]">
                                    {formatDate(job.posted_at)}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            {/* Pagination */}
            {jobs.last_page > 1 && (
                <div className="mt-6 flex justify-center gap-1">
                    {jobs.links.map((link, i) => {
                        const label = link.label.replace(/&laquo;/g, '\u00AB').replace(/&raquo;/g, '\u00BB').replace(/<[^>]*>/g, '');
                        return link.url ? (
                            <Link key={i} href={link.url} className={`px-3 py-1 text-xs ${link.active ? 'bg-[var(--color-text)] text-[var(--color-background)]' : 'text-[var(--color-text-secondary)]'}`} preserveScroll>
                                {label}
                            </Link>
                        ) : (
                            <span key={i} className="px-3 py-1 text-xs text-[var(--color-accent)]">{label}</span>
                        );
                    })}
                </div>
            )}
        </AdminLayout>
    );
}
