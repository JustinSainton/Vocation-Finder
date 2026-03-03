import AdminLayout from '../../../Layouts/AdminLayout';
import { Link } from '@inertiajs/react';

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Props {
    organization: {
        id: string;
        name: string;
        slug: string;
        type: string;
        subscription_status: string | null;
        created_at: string;
    };
    members: Member[];
    assessment_stats: {
        total: number;
        completed: number;
        this_period: number;
    };
}

export default function OrganizationShow({ organization, members, assessment_stats }: Props) {
    return (
        <AdminLayout title={organization.name}>
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">{organization.name}</h1>
                <Link
                    href={`/admin/organizations/${organization.id}/edit`}
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Edit
                </Link>
            </div>

            <div className="mt-6 space-y-2 text-sm">
                <p className="text-[var(--color-text-secondary)]">
                    Type: <span className="text-[var(--color-text)]">{organization.type}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Status: <span className="text-[var(--color-text)]">{organization.subscription_status ?? 'none'}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Created: <span className="text-[var(--color-text)]">{organization.created_at}</span>
                </p>
            </div>

            <div className="mt-8 grid grid-cols-3 gap-4">
                {[
                    { label: 'Total Assessments', value: assessment_stats.total },
                    { label: 'Completed', value: assessment_stats.completed },
                    { label: 'This Period', value: assessment_stats.this_period },
                ].map((stat) => (
                    <div key={stat.label} className="border border-[var(--color-border)] p-4">
                        <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">{stat.label}</p>
                        <p className="mt-1 font-serif text-xl text-[var(--color-text)]">{stat.value}</p>
                    </div>
                ))}
            </div>

            <div className="mt-10">
                <h2 className="text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Members ({members.length})
                </h2>
                <table className="mt-4 w-full text-sm">
                    <thead>
                        <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                            <th className="pb-2">Name</th>
                            <th className="pb-2">Email</th>
                            <th className="pb-2">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((m) => (
                            <tr key={m.id} className="border-b border-[var(--color-border)]">
                                <td className="py-3 text-[var(--color-text)]">{m.name}</td>
                                <td className="py-3 text-[var(--color-text-secondary)]">{m.email}</td>
                                <td className="py-3">
                                    <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                        {m.role}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
