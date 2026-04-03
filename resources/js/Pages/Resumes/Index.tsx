import AppLayout from '../../Layouts/AppLayout';
import { Link, usePage } from '@inertiajs/react';

interface Resume {
    id: string;
    status: 'generating' | 'ready' | 'failed';
    quality_score: number | null;
    created_at: string;
    job_listing: { id: string; title: string; company_name: string } | null;
}

interface Props {
    resumes: {
        data: Resume[];
        current_page: number;
        last_page: number;
    };
    coverLetters: {
        data: Array<{
            id: string;
            status: string;
            created_at: string;
            job_listing: { id: string; title: string; company_name: string } | null;
        }>;
    };
}

const STATUS_STYLES: Record<string, string> = {
    generating: 'text-[var(--color-accent)]',
    ready: 'text-[var(--color-text)]',
    failed: 'text-red-600',
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function ResumesIndex({ resumes, coverLetters }: Props) {
    return (
        <AppLayout title="Resumes & Cover Letters">
            <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                Resumes & Cover Letters
            </h1>
            <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                AI-generated documents tailored to your vocational calling.
            </p>

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Resumes */}
            <div className="mb-10">
                <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    Resumes
                </h2>

                {resumes.data.length === 0 ? (
                    <p className="mt-4 text-sm text-[var(--color-text-secondary)]">
                        No resumes generated yet. Browse jobs and generate a tailored resume with one click.
                    </p>
                ) : (
                    <div className="mt-4 space-y-3">
                        {resumes.data.map((resume) => (
                            <div
                                key={resume.id}
                                className="flex items-center justify-between border border-[var(--color-border)] px-5 py-4"
                            >
                                <div>
                                    <p className="text-sm text-[var(--color-text)]">
                                        {resume.job_listing
                                            ? `${resume.job_listing.title} at ${resume.job_listing.company_name}`
                                            : 'General Resume'}
                                    </p>
                                    <div className="mt-1 flex items-center gap-3 text-xs">
                                        <span className={STATUS_STYLES[resume.status] ?? ''}>
                                            {resume.status}
                                        </span>
                                        {resume.quality_score && (
                                            <span className="text-[var(--color-text-secondary)]">
                                                Quality: {Math.round(resume.quality_score)}/100
                                            </span>
                                        )}
                                        <span className="text-[var(--color-accent)]">
                                            {formatDate(resume.created_at)}
                                        </span>
                                    </div>
                                </div>
                                {resume.status === 'ready' && (
                                    <Link
                                        href={`/resumes/${resume.id}`}
                                        className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                                    >
                                        View
                                    </Link>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Cover Letters */}
            <div>
                <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    Cover Letters
                </h2>

                {coverLetters.data.length === 0 ? (
                    <p className="mt-4 text-sm text-[var(--color-text-secondary)]">
                        No cover letters generated yet.
                    </p>
                ) : (
                    <div className="mt-4 space-y-3">
                        {coverLetters.data.map((letter) => (
                            <div
                                key={letter.id}
                                className="flex items-center justify-between border border-[var(--color-border)] px-5 py-4"
                            >
                                <div>
                                    <p className="text-sm text-[var(--color-text)]">
                                        {letter.job_listing
                                            ? `${letter.job_listing.title} at ${letter.job_listing.company_name}`
                                            : 'General Cover Letter'}
                                    </p>
                                    <span className={`text-xs ${STATUS_STYLES[letter.status] ?? ''}`}>
                                        {letter.status}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
