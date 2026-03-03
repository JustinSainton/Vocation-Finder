import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';

interface CourseRow {
    id: string;
    title: string;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    category_name: string | null;
    modules_count: number;
    enrollments_count: number;
    estimated_duration: string | null;
    sort_order: number;
}

interface Props {
    courses: CourseRow[];
}

export default function AdminCoursesIndex({ courses }: Props) {
    const handleDelete = (slug: string, title: string) => {
        if (confirm(`Delete "${title}"? This action cannot be undone.`)) {
            router.delete(`/admin/courses/${slug}`);
        }
    };

    return (
        <AdminLayout title="Courses">
            <div className="mb-6 flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">Courses</h1>
                <Link
                    href="/admin/courses/create"
                    className="bg-[var(--color-text)] px-5 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                >
                    New course
                </Link>
            </div>

            {courses.length === 0 ? (
                <p className="py-16 text-center text-[var(--color-text-secondary)]">
                    No courses yet. Create your first course to get started.
                </p>
            ) : (
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-[var(--color-divider)] text-left">
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Title
                            </th>
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Category
                            </th>
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Modules
                            </th>
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Enrolled
                            </th>
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Status
                            </th>
                            <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {courses.map((course) => (
                            <tr
                                key={course.id}
                                className="border-b border-[var(--color-divider)]"
                            >
                                <td className="py-4 pr-4">
                                    <p className="font-serif text-[var(--color-text)]">
                                        {course.title}
                                    </p>
                                    {course.estimated_duration && (
                                        <p className="mt-1 font-sans text-xs text-[var(--color-accent)]">
                                            {course.estimated_duration}
                                        </p>
                                    )}
                                </td>
                                <td className="py-4 pr-4 font-sans text-sm text-[var(--color-text-secondary)]">
                                    {course.category_name ?? '--'}
                                </td>
                                <td className="py-4 pr-4 font-sans text-sm text-[var(--color-text)]">
                                    {course.modules_count}
                                </td>
                                <td className="py-4 pr-4 font-sans text-sm text-[var(--color-text)]">
                                    {course.enrollments_count}
                                </td>
                                <td className="py-4 pr-4">
                                    <span
                                        className={`inline-block font-sans text-xs uppercase tracking-wider ${
                                            course.is_published
                                                ? 'text-[var(--color-stone-700)]'
                                                : 'text-[var(--color-stone-400)]'
                                        }`}
                                    >
                                        {course.is_published ? 'Published' : 'Draft'}
                                    </span>
                                </td>
                                <td className="py-4">
                                    <div className="flex gap-3">
                                        <Link
                                            href={`/admin/courses/${course.slug}/edit`}
                                            className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            onClick={() =>
                                                handleDelete(course.slug, course.title)
                                            }
                                            className="font-sans text-sm text-[var(--color-stone-400)] hover:text-[var(--color-text)]"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </AdminLayout>
    );
}
