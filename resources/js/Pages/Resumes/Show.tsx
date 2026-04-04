import AppLayout from '../../Layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface Resume {
    id: string;
    status: string;
    quality_score: number | null;
    resume_data: {
        summary?: string;
        work?: Array<{ position: string; company: string; startDate: string; endDate: string; highlights?: string[] }>;
        education?: Array<{ institution: string; area: string; studyType: string }>;
        skills?: Array<{ name: string; keywords: string[] }>;
    };
    created_at: string;
    job_listing: { id: string; title: string; company_name: string } | null;
}

interface Props {
    resume: Resume;
}

export default function ResumeShow({ resume }: Props) {
    const data = resume.resume_data;

    return (
        <AppLayout title="Resume">
            <Link
                href="/resumes"
                className="font-sans text-xs tracking-wide text-[var(--color-text-secondary)] hover:text-[var(--color-text)]"
            >
                &larr; Back to Resumes
            </Link>

            <div className="mt-6 flex items-start justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        {resume.job_listing
                            ? `Resume for ${resume.job_listing.title}`
                            : 'General Resume'}
                    </h1>
                    {resume.job_listing && (
                        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                            {resume.job_listing.company_name}
                        </p>
                    )}
                </div>
                {resume.quality_score && (
                    <span className="border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-text)]">
                        Quality: {Math.round(resume.quality_score)}/100
                    </span>
                )}
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {data.summary && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Professional Summary
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-[var(--color-text)]">{data.summary}</p>
                </div>
            )}

            {data.work && data.work.length > 0 && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Experience
                    </h2>
                    <div className="mt-3 space-y-4">
                        {data.work.map((entry, i) => (
                            <div key={i}>
                                <p className="text-sm font-medium text-[var(--color-text)]">{entry.position}</p>
                                <p className="text-xs text-[var(--color-text-secondary)]">
                                    {entry.company} {'\u00B7'} {entry.startDate} {'\u2013'} {entry.endDate || 'Present'}
                                </p>
                                {entry.highlights && (
                                    <ul className="mt-1 list-disc pl-5">
                                        {entry.highlights.map((h, j) => (
                                            <li key={j} className="text-sm text-[var(--color-text-secondary)]">{h}</li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {data.education && data.education.length > 0 && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Education
                    </h2>
                    <div className="mt-3 space-y-2">
                        {data.education.map((entry, i) => (
                            <div key={i}>
                                <p className="text-sm font-medium text-[var(--color-text)]">
                                    {entry.studyType} in {entry.area}
                                </p>
                                <p className="text-xs text-[var(--color-text-secondary)]">{entry.institution}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {data.skills && data.skills.length > 0 && (
                <div className="mb-6">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Skills
                    </h2>
                    <div className="mt-2 space-y-1">
                        {data.skills.map((group, i) => (
                            <p key={i} className="text-sm text-[var(--color-text)]">
                                <span className="font-medium">{group.name}:</span>{' '}
                                <span className="text-[var(--color-text-secondary)]">{group.keywords.join(', ')}</span>
                            </p>
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
