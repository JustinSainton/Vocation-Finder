import AppLayout from '../../Layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface CourseCard {
    id: string;
    title: string;
    slug: string;
    short_description: string | null;
    estimated_duration: string | null;
    vocational_category_id: string | null;
    category_name: string | null;
    requires_personalization: boolean;
}

interface Category {
    id: string;
    name: string;
    slug: string;
}

interface Recommendation {
    course_id: string;
    course_slug: string;
    course_title: string;
    relevance: number;
    reason: string;
}

interface Props {
    categories: Category[];
    coursesByCategory: Record<string, CourseCard[]>;
    uncategorized: CourseCard[];
    recommendations: Recommendation[];
}

export default function CoursesIndex({ categories, coursesByCategory, uncategorized, recommendations }: Props) {
    return (
        <AppLayout title="Courses">
            <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Formation
            </p>

            <h1 className="mb-4 font-serif text-3xl tracking-tight text-[var(--color-text)]">
                Courses
            </h1>

            <p className="mb-10 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                Guided learning paths to deepen your vocational discernment and develop
                the skills aligned with your calling.
            </p>

            {/* Personalized Recommendations */}
            {recommendations.length > 0 && (
                <div className="mb-12">
                    <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Recommended for you
                    </p>
                    <div className="space-y-4">
                        {recommendations.map((rec) => (
                            <Link
                                key={rec.course_id}
                                href={`/courses/${rec.course_slug}`}
                                className="block border border-[var(--color-accent)] bg-[var(--color-surface)] p-6 transition-colors hover:border-[var(--color-text)]"
                            >
                                <h3 className="font-serif text-lg text-[var(--color-text)]">
                                    {rec.course_title}
                                </h3>
                                <p className="mt-2 text-sm italic leading-relaxed text-[var(--color-accent)]">
                                    {rec.reason}
                                </p>
                            </Link>
                        ))}
                    </div>

                    <div className="my-10 h-px bg-[var(--color-divider)]" />
                </div>
            )}

            {categories.map((category) => {
                const courses = coursesByCategory[category.id];
                if (!courses || courses.length === 0) return null;

                return (
                    <div key={category.id} className="mb-12">
                        <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            {category.name}
                        </p>
                        <div className="space-y-4">
                            {courses.map((course) => (
                                <CourseCardItem key={course.id} course={course} />
                            ))}
                        </div>
                    </div>
                );
            })}

            {uncategorized.length > 0 && (
                <div className="mb-12">
                    <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        General
                    </p>
                    <div className="space-y-4">
                        {uncategorized.map((course) => (
                            <CourseCardItem key={course.id} course={course} />
                        ))}
                    </div>
                </div>
            )}

            {categories.every((c) => !coursesByCategory[c.id]?.length) &&
                uncategorized.length === 0 && (
                    <p className="py-16 text-center text-lg text-[var(--color-text-secondary)]">
                        Courses are being prepared. Check back soon.
                    </p>
                )}
        </AppLayout>
    );
}

function CourseCardItem({ course }: { course: CourseCard }) {
    return (
        <Link
            href={`/courses/${course.slug}`}
            className="block border border-[var(--color-divider)] bg-[var(--color-surface)] p-6 transition-colors hover:border-[var(--color-stone-400)]"
        >
            <div className="flex items-start justify-between">
                <h3 className="font-serif text-lg text-[var(--color-text)]">{course.title}</h3>
                {course.requires_personalization && (
                    <span className="ml-3 shrink-0 font-sans text-[10px] uppercase tracking-widest text-[var(--color-accent)]">
                        Personalized
                    </span>
                )}
            </div>
            {course.short_description && (
                <p className="mt-2 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                    {course.short_description}
                </p>
            )}
            {course.estimated_duration && (
                <p className="mt-3 font-sans text-xs text-[var(--color-accent)]">
                    {course.estimated_duration}
                </p>
            )}
        </Link>
    );
}
