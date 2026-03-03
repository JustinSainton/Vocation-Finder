import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { useEffect, useRef } from 'react';

interface PathwayCourse {
    id: string;
    title: string;
    slug: string;
    short_description: string | null;
    estimated_duration: string | null;
    category_name: string | null;
    difficulty_level: string;
    rationale: string | null;
    enrollment_status: string | null;
}

interface Phase {
    description: string;
    courses: PathwayCourse[];
}

interface Pathway {
    id: string;
    status: 'generating' | 'ready' | 'failed';
    pathway_summary: string | null;
    generated_at: string | null;
    phases: {
        discovery: Phase;
        deepening: Phase;
        integration: Phase;
        application: Phase;
    };
}

interface Props {
    pathway: Pathway;
    progress: {
        total: number;
        completed: number;
    };
}

const PHASE_LABELS: Record<string, string> = {
    discovery: 'Discovery',
    deepening: 'Deepening',
    integration: 'Integration',
    application: 'Application',
};

const PHASE_ORDER = ['discovery', 'deepening', 'integration', 'application'] as const;

const DIFFICULTY_LABELS: Record<string, string> = {
    foundational: 'Foundational',
    intermediate: 'Intermediate',
    advanced: 'Advanced',
};

export default function PathwayShow({ pathway, progress }: Props) {
    const pollInterval = useRef<ReturnType<typeof setInterval> | null>(null);

    // Poll while generating
    useEffect(() => {
        if (pathway.status === 'generating') {
            pollInterval.current = setInterval(() => {
                router.reload({ only: ['pathway', 'progress'] });
            }, 5000);
        }
        return () => {
            if (pollInterval.current) clearInterval(pollInterval.current);
        };
    }, [pathway.status]);

    if (pathway.status === 'generating') {
        return (
            <AppLayout title="Building Your Path">
                <div className="py-20 text-center">
                    <div className="mx-auto mb-8 h-8 w-8 animate-spin border-2 border-[var(--color-divider)] border-t-[var(--color-text)]" />
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Curating your learning path
                    </h1>
                    <p className="mt-4 text-[var(--color-text-secondary)]">
                        We're selecting and sequencing courses based on your vocational
                        profile. This takes about a minute.
                    </p>
                </div>
            </AppLayout>
        );
    }

    if (pathway.status === 'failed') {
        return (
            <AppLayout title="Learning Path">
                <div className="py-20 text-center">
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Something went wrong
                    </h1>
                    <p className="mt-4 text-[var(--color-text-secondary)]">
                        We weren't able to generate your learning path. Please try
                        completing another assessment, or check back later.
                    </p>
                    <Link
                        href="/dashboard"
                        className="mt-8 inline-block border border-[var(--color-text)] px-6 py-3 font-sans text-sm tracking-wide text-[var(--color-text)] transition-colors hover:bg-[var(--color-text)] hover:text-[var(--color-background)]"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </AppLayout>
        );
    }

    const progressPercent =
        progress.total > 0
            ? Math.round((progress.completed / progress.total) * 100)
            : 0;

    return (
        <AppLayout title="Your Learning Path">
            <div>
                <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    Your learning path
                </p>

                <h1 className="mt-3 font-serif text-2xl tracking-tight text-[var(--color-text)]">
                    A curriculum shaped for you
                </h1>

                {pathway.pathway_summary && (
                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        {pathway.pathway_summary}
                    </p>
                )}

                {/* Progress */}
                <div className="mt-8">
                    <div className="flex items-center justify-between font-sans text-sm">
                        <span className="text-[var(--color-text-secondary)]">
                            {progress.completed} of {progress.total} courses completed
                        </span>
                        <span className="text-[var(--color-accent)]">
                            {progressPercent}%
                        </span>
                    </div>
                    <div className="mt-2 h-1 w-full bg-[var(--color-divider)]">
                        <div
                            className="h-1 bg-[var(--color-text)] transition-all duration-500"
                            style={{ width: `${progressPercent}%` }}
                        />
                    </div>
                </div>

                <div className="my-8 h-px bg-[var(--color-divider)]" />

                {/* Phases */}
                {PHASE_ORDER.map((phaseKey, phaseIndex) => {
                    const phase = pathway.phases[phaseKey];
                    if (!phase || phase.courses.length === 0) return null;

                    return (
                        <div key={phaseKey} className={phaseIndex > 0 ? 'mt-12' : ''}>
                            <div className="flex items-center gap-3">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-[var(--color-text)] font-sans text-xs text-[var(--color-background)]">
                                    {phaseIndex + 1}
                                </span>
                                <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                    {PHASE_LABELS[phaseKey]}
                                </p>
                            </div>

                            {phase.description && (
                                <p className="mt-3 text-[var(--color-text-secondary)]">
                                    {phase.description}
                                </p>
                            )}

                            <div className="mt-4 space-y-3">
                                {phase.courses.map((course) => (
                                    <Link
                                        key={course.id}
                                        href={`/courses/${course.slug}`}
                                        className="block border border-[var(--color-divider)] p-5 transition-colors hover:border-[var(--color-text)]"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1">
                                                <p className="font-serif text-[var(--color-text)]">
                                                    {course.title}
                                                </p>
                                                <div className="mt-1 flex items-center gap-3">
                                                    {course.category_name && (
                                                        <span className="font-sans text-xs text-[var(--color-accent)]">
                                                            {course.category_name}
                                                        </span>
                                                    )}
                                                    {course.estimated_duration && (
                                                        <span className="font-sans text-xs text-[var(--color-accent)]">
                                                            {course.estimated_duration}
                                                        </span>
                                                    )}
                                                    <span className="font-sans text-xs text-[var(--color-accent)]">
                                                        {DIFFICULTY_LABELS[course.difficulty_level] ?? course.difficulty_level}
                                                    </span>
                                                </div>
                                            </div>
                                            {course.enrollment_status === 'completed' && (
                                                <span className="shrink-0 border border-[var(--color-text)] px-3 py-1 font-sans text-xs tracking-wide text-[var(--color-text)]">
                                                    Completed
                                                </span>
                                            )}
                                        </div>
                                        {course.rationale && (
                                            <p className="mt-3 border-t border-[var(--color-divider)] pt-3 font-sans text-sm leading-relaxed text-[var(--color-text-secondary)]">
                                                {course.rationale}
                                            </p>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    );
                })}

                <div className="my-8 h-px bg-[var(--color-divider)]" />

                <Link
                    href="/dashboard"
                    className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                >
                    &larr; Dashboard
                </Link>
            </div>
        </AppLayout>
    );
}
