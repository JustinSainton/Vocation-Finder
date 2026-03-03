import AdminLayout from '../../Layouts/AdminLayout';
import { Link } from '@inertiajs/react';

interface RecentAssessment {
    id: string;
    user_name: string;
    mode: string;
    status: string;
    created_at: string;
}

interface Props {
    stats: {
        total_users: number;
        total_assessments: number;
        completed_assessments: number;
        active_organizations: number;
    };
    recent_assessments: RecentAssessment[];
}

export default function Dashboard({ stats, recent_assessments }: Props) {
    return (
        <AdminLayout title="Dashboard">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Dashboard</h1>

            <div className="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {[
                    { label: 'Total Users', value: stats.total_users },
                    { label: 'Total Assessments', value: stats.total_assessments },
                    { label: 'Completed', value: stats.completed_assessments },
                    { label: 'Active Orgs', value: stats.active_organizations },
                ].map((stat) => (
                    <div
                        key={stat.label}
                        className="border border-[var(--color-border)] p-5"
                    >
                        <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                            {stat.label}
                        </p>
                        <p className="mt-2 font-serif text-2xl text-[var(--color-text)]">
                            {stat.value}
                        </p>
                    </div>
                ))}
            </div>

            <div className="mt-12">
                <h2 className="text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Recent Assessments
                </h2>
                <table className="mt-4 w-full text-sm">
                    <thead>
                        <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                            <th className="pb-2">User</th>
                            <th className="pb-2">Mode</th>
                            <th className="pb-2">Status</th>
                            <th className="pb-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recent_assessments.map((a) => (
                            <tr
                                key={a.id}
                                className="border-b border-[var(--color-border)]"
                            >
                                <td className="py-3 text-[var(--color-text)]">
                                    {a.user_name}
                                </td>
                                <td className="py-3 text-[var(--color-text-secondary)]">
                                    {a.mode}
                                </td>
                                <td className="py-3">
                                    <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                        {a.status}
                                    </span>
                                </td>
                                <td className="py-3 text-[var(--color-text-secondary)]">
                                    {a.created_at}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
