import AppLayout from '../../Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

interface Module {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    sort_order: number;
}

interface CourseDetail {
    id: string;
    title: string;
    slug: string;
    description: string;
    short_description: string | null;
    content_blocks: ContentBlock[] | null;
    estimated_duration: string | null;
    category_name: string | null;
    requires_personalization: boolean;
    modules: Module[];
}

interface Enrollment {
    id: string;
    status: string;
    current_module_id: string | null;
    progress: Record<string, unknown> | null;
    assessment_id: string | null;
}

interface PersonalizationStatus {
    total: number;
    ready: number;
    complete: boolean;
}

interface ContentBlock {
    type: string;
    content?: string;
    url?: string;
    title?: string;
    prompt?: string;
    question?: string;
    options?: string[];
}

interface Props {
    course: CourseDetail;
    enrollment: Enrollment | null;
    personalizationStatus: PersonalizationStatus | null;
}

export default function CourseShow({ course, enrollment, personalizationStatus }: Props) {
    const isEnrolled = !!enrollment;

    const handleEnroll = () => {
        router.post(`/courses/${course.slug}/enroll`);
    };

    const currentModuleIndex = enrollment?.current_module_id
        ? course.modules.findIndex((m) => m.id === enrollment.current_module_id)
        : -1;

    return (
        <AppLayout title={course.title}>
            {course.category_name && (
                <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    {course.category_name}
                </p>
            )}

            <h1 className="mb-4 font-serif text-3xl tracking-tight text-[var(--color-text)]">
                {course.title}
            </h1>

            {course.estimated_duration && (
                <p className="mb-6 font-sans text-sm text-[var(--color-accent)]">
                    {course.estimated_duration}
                </p>
            )}

            <p className="text-lg leading-relaxed text-[var(--color-text)]">
                {course.description}
            </p>

            {/* Personalization notice */}
            {course.requires_personalization && (
                <div className="mt-6 border-l-[3px] border-[var(--color-accent)] bg-[var(--color-surface)] p-5">
                    <p className="font-sans text-sm text-[var(--color-text)]">
                        This course adapts its content to your vocational profile.
                        {!isEnrolled && ' Enroll to begin your personalized learning path.'}
                    </p>
                    {personalizationStatus && !personalizationStatus.complete && (
                        <p className="mt-2 font-sans text-xs text-[var(--color-accent)]">
                            Personalizing: {personalizationStatus.ready} of {personalizationStatus.total} modules ready
                        </p>
                    )}
                </div>
            )}

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Modules */}
            {course.modules.length > 0 && (
                <>
                    <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Modules
                    </p>
                    <div className="space-y-3">
                        {course.modules.map((module, index) => {
                            const isCompleted = currentModuleIndex > index;
                            const isCurrent = currentModuleIndex === index;

                            return (
                                <Link
                                    key={module.id}
                                    href={`/courses/${course.slug}/modules/${module.slug}`}
                                    className="flex items-center gap-4 border border-[var(--color-divider)] bg-[var(--color-surface)] p-5 transition-colors hover:border-[var(--color-stone-400)]"
                                >
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center bg-[var(--color-text)] font-sans text-xs text-[var(--color-background)]">
                                        {isCompleted ? '\u2713' : index + 1}
                                    </span>
                                    <div className="flex-1">
                                        <p className="font-serif text-[var(--color-text)]">
                                            {module.title}
                                        </p>
                                        {module.description && (
                                            <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                                                {module.description}
                                            </p>
                                        )}
                                    </div>
                                    {isCurrent && (
                                        <span className="font-sans text-xs text-[var(--color-accent)]">
                                            Current
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </div>

                    <div className="my-8 h-px bg-[var(--color-divider)]" />
                </>
            )}

            {/* Enroll / Continue */}
            {isEnrolled ? (
                <Link
                    href={
                        enrollment.current_module_id && course.modules.length > 0
                            ? `/courses/${course.slug}/modules/${
                                  course.modules.find(
                                      (m) => m.id === enrollment.current_module_id
                                  )?.slug ?? course.modules[0].slug
                              }`
                            : course.modules.length > 0
                              ? `/courses/${course.slug}/modules/${course.modules[0].slug}`
                              : `/courses/${course.slug}`
                    }
                    className="block w-full bg-[var(--color-text)] py-4 text-center font-sans text-sm tracking-wide text-[var(--color-background)]"
                >
                    Continue learning &rarr;
                </Link>
            ) : (
                <button
                    onClick={handleEnroll}
                    className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                >
                    {course.requires_personalization
                        ? 'Enroll \u2014 we\u2019ll personalize this for you'
                        : 'Enroll in this course'}{' '}
                    &rarr;
                </button>
            )}

            <div className="mt-4">
                <Link
                    href="/courses"
                    className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                >
                    &larr; All courses
                </Link>
            </div>
        </AppLayout>
    );
}
