import AppLayout from '../../Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';

interface Category {
    slug: string;
    name: string;
    relevance: number;
}

interface JobDetail {
    id: string;
    title: string;
    company_name: string;
    company_url: string | null;
    location: string | null;
    is_remote: boolean;
    salary_min: number | null;
    salary_max: number | null;
    salary_currency: string;
    description: string | null;
    required_skills: string[] | null;
    source_url: string | null;
    source: string;
    posted_at: string | null;
    match_percent: number | null;
    is_saved: boolean;
    categories: Category[];
}

interface Props {
    job: JobDetail;
}

function formatSalary(min: number | null, max: number | null): string {
    const fmt = (n: number) => `$${(n / 1000).toFixed(0)}k`;
    if (min && max) return `${fmt(min)} \u2013 ${fmt(max)}`;
    if (min) return `${fmt(min)}+`;
    if (max) return `Up to ${fmt(max)}`;
    return 'Not specified';
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function JobShow({ job }: Props) {
    const { auth } = usePage<{ auth: { user: any } }>().props;

    const handleSave = () => {
        if (job.is_saved) {
            router.delete(`/jobs/${job.id}/save`, { preserveScroll: true });
        } else {
            router.post(`/jobs/${job.id}/save`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={job.title}>
            <Link
                href="/jobs"
                className="font-sans text-xs tracking-wide text-[var(--color-text-secondary)] hover:text-[var(--color-text)]"
            >
                &larr; Back to Jobs
            </Link>

            {/* Header */}
            <div className="mt-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                            {job.title}
                        </h1>
                        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                            {job.company_url ? (
                                <a
                                    href={job.company_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline hover:text-[var(--color-text)]"
                                >
                                    {job.company_name}
                                </a>
                            ) : (
                                job.company_name
                            )}
                            {job.location && ` \u00B7 ${job.location}`}
                            {job.is_remote && ' \u00B7 Remote'}
                        </p>
                    </div>
                    {job.match_percent !== null && (
                        <span className={`shrink-0 px-3 py-1 text-sm font-medium ${
                            job.match_percent >= 80
                                ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                                : 'border border-[var(--color-text)] text-[var(--color-text)]'
                        }`}>
                            {job.match_percent}% match
                        </span>
                    )}
                </div>

                {/* Meta */}
                <div className="mt-4 flex flex-wrap gap-4 text-xs text-[var(--color-text-secondary)]">
                    {(job.salary_min || job.salary_max) && (
                        <span>{formatSalary(job.salary_min, job.salary_max)}</span>
                    )}
                    {job.posted_at && <span>Posted {formatDate(job.posted_at)}</span>}
                    <span className="capitalize">via {job.source}</span>
                </div>
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Vocational Categories */}
            {job.categories.length > 0 && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Vocational Pathways
                    </h2>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {job.categories.map((cat) => (
                            <span
                                key={cat.slug}
                                className="border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-text)]"
                            >
                                {cat.name}
                                <span className="ml-1 text-[var(--color-accent)]">
                                    {Math.round(cat.relevance * 100)}%
                                </span>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Skills */}
            {job.required_skills && job.required_skills.length > 0 && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Required Skills
                    </h2>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {job.required_skills.map((skill, i) => (
                            <span
                                key={i}
                                className="border border-[var(--color-border)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]"
                            >
                                {typeof skill === 'string' ? skill : (skill as any).name}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Description */}
            {job.description && (
                <div className="mb-8">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Description
                    </h2>
                    <div
                        className="prose-sm mt-3 max-w-none text-sm leading-relaxed text-[var(--color-text-secondary)] [&_ul]:list-disc [&_ul]:pl-5 [&_li]:mb-1 [&_p]:mb-3 [&_strong]:text-[var(--color-text)]"
                    >
                        {/* Render plain text with line breaks */}
                        {job.description.split('\n').map((line, i) => (
                            <p key={i}>{line}</p>
                        ))}
                    </div>
                </div>
            )}

            {/* Actions */}
            <div className="flex gap-3">
                {job.source_url && (
                    <a
                        href={job.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)]"
                    >
                        Apply on {job.source}
                    </a>
                )}
                {auth.user && (
                    <button
                        onClick={handleSave}
                        className="border border-[var(--color-border)] px-6 py-3 text-xs tracking-wide text-[var(--color-text)]"
                    >
                        {job.is_saved ? 'Unsave' : 'Save Job'}
                    </button>
                )}
            </div>
        </AppLayout>
    );
}
