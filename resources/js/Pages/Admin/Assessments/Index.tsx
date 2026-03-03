import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Assessment {
    id: string;
    user: { id: string; name: string; email: string } | null;
    mode: string;
    status: string;
    created_at: string;
}

interface Props {
    assessments: {
        data: Assessment[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        status: string | null;
        search: string | null;
    };
}

export default function AssessmentsIndex({ assessments, filters }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = () => {
        router.get('/admin/assessments', { status: status || undefined, search: search || undefined }, { preserveState: true });
    };

    return (
        <AdminLayout title="Assessments">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Assessments</h1>

            <div className="mt-6 flex gap-4">
                <select
                    value={status}
                    onChange={(e) => { setStatus(e.target.value); }}
                    className="border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                >
                    <option value="">All statuses</option>
                    <option value="in_progress">In Progress</option>
                    <option value="analyzing">Analyzing</option>
                    <option value="completed">Completed</option>
                </select>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                    placeholder="Search by user..."
                    className="flex-1 border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] placeholder-[var(--color-text-secondary)] focus:outline-none"
                />
                <button
                    onClick={applyFilters}
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Filter
                </button>
            </div>

            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th className="pb-2">User</th>
                        <th className="pb-2">Mode</th>
                        <th className="pb-2">Status</th>
                        <th className="pb-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    {assessments.data.map((a) => (
                        <tr key={a.id} className="border-b border-[var(--color-border)]">
                            <td className="py-3">
                                <Link
                                    href={`/admin/assessments/${a.id}`}
                                    className="text-[var(--color-text)] underline decoration-[var(--color-border)] hover:decoration-[var(--color-text)]"
                                >
                                    {a.user?.name ?? 'Guest'}
                                </Link>
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{a.mode}</td>
                            <td className="py-3">
                                <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                    {a.status}
                                </span>
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{a.created_at}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="mt-6 flex items-center gap-4 text-sm">
                {assessments.prev_page_url && (
                    <Link href={assessments.prev_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Previous
                    </Link>
                )}
                <span className="text-[var(--color-text-secondary)]">
                    Page {assessments.current_page} of {assessments.last_page}
                </span>
                {assessments.next_page_url && (
                    <Link href={assessments.next_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Next
                    </Link>
                )}
            </div>
        </AdminLayout>
    );
}
