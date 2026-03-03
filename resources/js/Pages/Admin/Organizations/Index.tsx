import AdminLayout from '../../../Layouts/AdminLayout';
import { Link } from '@inertiajs/react';

interface Organization {
    id: string;
    name: string;
    type: string;
    subscription_status: string | null;
    users_count: number;
    assessments_count: number;
}

interface Props {
    organizations: {
        data: Organization[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}

export default function OrganizationsIndex({ organizations }: Props) {
    return (
        <AdminLayout title="Organizations">
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">Organizations</h1>
                <Link
                    href="/admin/organizations/create"
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Create
                </Link>
            </div>

            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th className="pb-2">Name</th>
                        <th className="pb-2">Type</th>
                        <th className="pb-2">Members</th>
                        <th className="pb-2">Assessments</th>
                        <th className="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {organizations.data.map((org) => (
                        <tr key={org.id} className="border-b border-[var(--color-border)]">
                            <td className="py-3">
                                <Link
                                    href={`/admin/organizations/${org.id}`}
                                    className="text-[var(--color-text)] underline decoration-[var(--color-border)] hover:decoration-[var(--color-text)]"
                                >
                                    {org.name}
                                </Link>
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{org.type}</td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{org.users_count}</td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{org.assessments_count}</td>
                            <td className="py-3">
                                <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                    {org.subscription_status ?? 'none'}
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="mt-6 flex items-center gap-4 text-sm">
                {organizations.prev_page_url && (
                    <Link href={organizations.prev_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Previous
                    </Link>
                )}
                <span className="text-[var(--color-text-secondary)]">
                    Page {organizations.current_page} of {organizations.last_page}
                </span>
                {organizations.next_page_url && (
                    <Link href={organizations.next_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Next
                    </Link>
                )}
            </div>
        </AdminLayout>
    );
}
