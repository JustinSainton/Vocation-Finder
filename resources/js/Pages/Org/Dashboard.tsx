import OrgLayout from '../../Layouts/OrgLayout';

interface OrgRef {
    id: string;
    name: string;
    slug: string;
    subscription_status: string | null;
}

interface Stats {
    member_count: number;
    member_limit: number;
    assessments_this_period: number;
    assessments_limit: number;
    total_assessments: number;
    completed_assessments: number;
    completion_rate: number;
}

interface Activity {
    id: string;
    user_name: string;
    user_email: string | null;
    status: string;
    mode: string;
    created_at: string;
    completed_at: string | null;
}

interface Props {
    organization: OrgRef;
    stats: Stats;
    recentActivity: Activity[];
}

export default function OrgDashboard({ organization, stats, recentActivity }: Props) {
    return (
        <OrgLayout title="Dashboard" orgName={organization.name} orgSlug={organization.slug}>
            <h1 className="mb-8 font-serif text-2xl text-[var(--color-text)]">Dashboard</h1>

            {/* Stats cards */}
            <div className="mb-10 grid grid-cols-4 gap-4">
                <StatCard
                    label="Members"
                    value={`${stats.member_count} / ${stats.member_limit}`}
                />
                <StatCard
                    label="Assessments this period"
                    value={`${stats.assessments_this_period} / ${stats.assessments_limit}`}
                />
                <StatCard
                    label="Total completed"
                    value={String(stats.completed_assessments)}
                />
                <StatCard
                    label="Completion rate"
                    value={`${stats.completion_rate}%`}
                />
            </div>

            {/* Recent activity */}
            <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Recent Activity
            </p>

            {recentActivity.length === 0 ? (
                <p className="py-8 text-center text-[var(--color-text-secondary)]">
                    No assessment activity yet.
                </p>
            ) : (
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-[var(--color-divider)] text-left">
                            <Th>Name</Th>
                            <Th>Mode</Th>
                            <Th>Status</Th>
                            <Th>Started</Th>
                            <Th>Completed</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {recentActivity.map((item) => (
                            <tr
                                key={item.id}
                                className="border-b border-[var(--color-divider)]"
                            >
                                <td className="py-3 pr-4">
                                    <p className="text-sm text-[var(--color-text)]">
                                        {item.user_name}
                                    </p>
                                    {item.user_email && (
                                        <p className="text-xs text-[var(--color-accent)]">
                                            {item.user_email}
                                        </p>
                                    )}
                                </td>
                                <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text-secondary)]">
                                    {item.mode}
                                </td>
                                <td className="py-3 pr-4">
                                    <StatusBadge status={item.status} />
                                </td>
                                <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text-secondary)]">
                                    {item.created_at}
                                </td>
                                <td className="py-3 font-sans text-sm text-[var(--color-text-secondary)]">
                                    {item.completed_at ?? '--'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </OrgLayout>
    );
}

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="border border-[var(--color-divider)] bg-[var(--color-surface)] p-5">
            <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                {label}
            </p>
            <p className="mt-2 font-serif text-2xl text-[var(--color-text)]">{value}</p>
        </div>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return (
        <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
            {children}
        </th>
    );
}

function StatusBadge({ status }: { status: string }) {
    const color =
        status === 'completed'
            ? 'text-[var(--color-stone-700)]'
            : status === 'analyzing'
              ? 'text-[var(--color-accent)]'
              : 'text-[var(--color-stone-400)]';

    return (
        <span className={`font-sans text-xs uppercase tracking-wider ${color}`}>
            {status}
        </span>
    );
}
