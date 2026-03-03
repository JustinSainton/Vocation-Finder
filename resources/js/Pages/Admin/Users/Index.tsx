import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface User {
    id: string;
    name: string;
    email: string;
    role: string;
    assessments_count: number;
    created_at: string;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string | null };
}

export default function UsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/users', { search }, { preserveState: true });
    };

    return (
        <AdminLayout title="Users">
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">Users</h1>
            </div>

            <form onSubmit={handleSearch} className="mt-6">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search by name or email..."
                    className="w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] placeholder-[var(--color-text-secondary)] focus:outline-none"
                />
            </form>

            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th className="pb-2">Name</th>
                        <th className="pb-2">Email</th>
                        <th className="pb-2">Role</th>
                        <th className="pb-2">Assessments</th>
                        <th className="pb-2">Created</th>
                    </tr>
                </thead>
                <tbody>
                    {users.data.map((user) => (
                        <tr key={user.id} className="border-b border-[var(--color-border)]">
                            <td className="py-3">
                                <Link
                                    href={`/admin/users/${user.id}`}
                                    className="text-[var(--color-text)] underline decoration-[var(--color-border)] hover:decoration-[var(--color-text)]"
                                >
                                    {user.name}
                                </Link>
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{user.email}</td>
                            <td className="py-3">
                                <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                    {user.role}
                                </span>
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{user.assessments_count}</td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{user.created_at}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="mt-6 flex items-center gap-4 text-sm">
                {users.prev_page_url && (
                    <Link href={users.prev_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Previous
                    </Link>
                )}
                <span className="text-[var(--color-text-secondary)]">
                    Page {users.current_page} of {users.last_page}
                </span>
                {users.next_page_url && (
                    <Link href={users.next_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Next
                    </Link>
                )}
            </div>
        </AdminLayout>
    );
}
