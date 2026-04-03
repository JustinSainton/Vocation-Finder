import AppLayout from '../../Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function CareerProfileImport() {
    const { data, setData, post, processing, errors, progress } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/career-profile/import', {
            forceFormData: true,
        });
    };

    return (
        <AppLayout title="Import Career Profile">
            <Link
                href="/career-profile"
                className="font-sans text-xs tracking-wide text-[var(--color-text-secondary)] hover:text-[var(--color-text)]"
            >
                &larr; Back to Career Profile
            </Link>

            <h1 className="mt-6 font-serif text-2xl tracking-tight text-[var(--color-text)]">
                Import Your Resume
            </h1>

            <p className="mt-3 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                Upload a PDF of your resume or LinkedIn profile export. We'll extract your work
                history, education, and skills to build your career profile.
            </p>

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            <div className="space-y-6">
                <div>
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        From LinkedIn
                    </h2>
                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                        Open LinkedIn, go to your profile, click "More" &rarr; "Save to PDF",
                        then upload the downloaded file here.
                    </p>
                </div>

                <div>
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        From any resume
                    </h2>
                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                        Upload any resume in PDF format. We'll do our best to extract the
                        structured data.
                    </p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="mt-8">
                <label className="block">
                    <span className="font-sans text-sm text-[var(--color-text)]">
                        Resume PDF
                    </span>
                    <input
                        type="file"
                        accept=".pdf"
                        onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        className="mt-2 block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:border file:border-[var(--color-border)] file:bg-[var(--color-background)] file:px-4 file:py-2 file:text-xs file:tracking-wide file:text-[var(--color-text)]"
                    />
                    {errors.file && (
                        <p className="mt-1 text-xs text-red-600">{errors.file}</p>
                    )}
                </label>

                {progress && (
                    <div className="mt-4">
                        <div className="h-1 w-full bg-[var(--color-border)]">
                            <div
                                className="h-1 bg-[var(--color-text)] transition-all duration-300"
                                style={{ width: `${progress.percentage}%` }}
                            />
                        </div>
                        <p className="mt-1 text-xs text-[var(--color-text-secondary)]">
                            Uploading... {progress.percentage}%
                        </p>
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing || !data.file}
                    className="mt-6 bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)] disabled:opacity-50"
                >
                    {processing ? 'Uploading...' : 'Upload Resume'}
                </button>
            </form>
        </AppLayout>
    );
}
