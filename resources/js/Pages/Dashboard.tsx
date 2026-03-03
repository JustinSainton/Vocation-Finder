import { Link, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

interface Assessment {
    id: string;
    status: 'in_progress' | 'analyzing' | 'completed' | 'failed';
    mode: 'written' | 'conversation';
    created_at: string;
}

interface PathwayData {
    id: string;
    status: 'generating' | 'ready' | 'failed';
    pathway_summary: string | null;
    total_courses: number;
    completed_courses: number;
}

interface Props {
    assessments: Assessment[];
    pathway: PathwayData | null;
}

const STATUS_LABELS: Record<Assessment['status'], string> = {
    in_progress: 'In progress',
    analyzing: 'Analyzing',
    completed: 'Completed',
    failed: 'Failed',
};

const STATUS_COLORS: Record<Assessment['status'], string> = {
    in_progress: 'border-[var(--color-accent)] text-[var(--color-text-secondary)]',
    analyzing: 'border-[var(--color-accent)] text-[var(--color-text-secondary)]',
    completed: 'border-[var(--color-text)] text-[var(--color-text)]',
    failed: 'border-red-300 text-red-700',
};

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function Dashboard({ assessments, pathway }: Props) {
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;

    return (
        <AppLayout title="Dashboard">
            <div>
                <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                    Welcome back, {auth.user.name}
                </h1>

                <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                    Your vocational discernment journey.
                </p>

                <div className="my-8 h-px bg-[var(--color-divider)]" />

                {/* Learning Path */}
                {pathway && pathway.status === 'ready' && (
                    <>
                        <div className="space-y-4">
                            <h2 className="font-sans text-sm tracking-wide text-[var(--color-text-secondary)]">
                                Your learning path
                            </h2>

                            <Link
                                href={`/pathway/${pathway.id}`}
                                className="block border border-[var(--color-divider)] p-5 transition-colors hover:border-[var(--color-text)]"
                            >
                                {pathway.pathway_summary && (
                                    <p className="text-[var(--color-text)]">
                                        {pathway.pathway_summary}
                                    </p>
                                )}
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="font-sans text-sm text-[var(--color-text-secondary)]">
                                        {pathway.completed_courses} of {pathway.total_courses} courses completed
                                    </span>
                                    <span className="font-sans text-xs text-[var(--color-accent)]">
                                        View path &rarr;
                                    </span>
                                </div>
                                <div className="mt-2 h-1 w-full bg-[var(--color-divider)]">
                                    <div
                                        className="h-1 bg-[var(--color-text)] transition-all duration-500"
                                        style={{
                                            width: `${pathway.total_courses > 0 ? Math.round((pathway.completed_courses / pathway.total_courses) * 100) : 0}%`,
                                        }}
                                    />
                                </div>
                            </Link>
                        </div>

                        <div className="my-8 h-px bg-[var(--color-divider)]" />
                    </>
                )}

                {pathway && pathway.status === 'generating' && (
                    <>
                        <div className="border border-[var(--color-divider)] p-5">
                            <div className="flex items-center gap-3">
                                <div className="h-4 w-4 animate-spin border border-[var(--color-divider)] border-t-[var(--color-text)]" />
                                <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                    Building your personalized learning path...
                                </p>
                            </div>
                        </div>

                        <div className="my-8 h-px bg-[var(--color-divider)]" />
                    </>
                )}

                {assessments.length > 0 ? (
                    <div className="space-y-4">
                        <h2 className="font-sans text-sm tracking-wide text-[var(--color-text-secondary)]">
                            Your assessments
                        </h2>

                        {assessments.map((assessment) => (
                            <Link
                                key={assessment.id}
                                href={
                                    assessment.status === 'completed'
                                        ? `/assessment/${assessment.id}/results`
                                        : `/assessment`
                                }
                                className="block border border-[var(--color-divider)] p-5 transition-colors hover:border-[var(--color-text)]"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                            {assessment.mode === 'conversation'
                                                ? 'Conversation'
                                                : 'Written'}{' '}
                                            assessment
                                        </p>
                                        <p className="mt-1 font-sans text-xs text-[var(--color-accent)]">
                                            {formatDate(assessment.created_at)}
                                        </p>
                                    </div>
                                    <span
                                        className={`inline-block border px-3 py-1 font-sans text-xs tracking-wide ${STATUS_COLORS[assessment.status]}`}
                                    >
                                        {STATUS_LABELS[assessment.status]}
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        You haven&apos;t started any assessments yet.
                    </p>
                )}

                <div className="mt-10">
                    <Link
                        href="/assessment"
                        className="block w-full bg-[var(--color-text)] py-4 text-center font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                    >
                        Start new assessment &rarr;
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
