import AdminLayout from '../../Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import {
    AreaChart,
    Area,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    CartesianGrid,
} from 'recharts';

interface Kpis {
    total_users: number;
    new_users_30d: number;
    active_orgs: number;
    total_assessments: number;
    completed_assessments: number;
    completion_rate: number;
}

interface VolumePoint {
    period: string;
    started: number;
    completed: number;
}

interface SurveyAnalysis {
    avg_clarity_delta: number;
    avg_action_delta: number;
    paired_count: number;
    clarity_before: { clarity_score: number; count: number }[];
    clarity_after: { clarity_score: number; count: number }[];
}

interface DomainEntry {
    primary_domain: string;
    count: number;
}

interface RecentAssessment {
    id: string;
    user: { name: string; email: string } | null;
    organization: string | null;
    mode: string;
    status: string;
    locale: string;
    created_at: string;
}

interface OrgUsage {
    id: string;
    name: string;
    slug: string;
    member_count: number;
    assessments_used: number;
    assessments_limit: number;
    member_limit: number;
}

interface Props {
    kpis: Kpis;
    assessmentVolume: VolumePoint[];
    surveyAnalysis: SurveyAnalysis;
    domainDistribution: DomainEntry[];
    recentAssessments: RecentAssessment[];
    orgUsage: OrgUsage[];
}

const CHART_COLORS = {
    primary: '#57534E',
    secondary: '#A8A29E',
    accent: '#78716C',
    grid: '#E7E5E4',
    positive: '#16a34a',
    negative: '#dc2626',
};

function KpiCard({ label, value, subtitle }: { label: string; value: string | number; subtitle?: string }) {
    return (
        <div className="border border-[var(--color-border)] p-5">
            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                {label}
            </p>
            <p className="mt-2 font-serif text-2xl text-[var(--color-text)]">{value}</p>
            {subtitle && (
                <p className="mt-1 text-xs text-[var(--color-accent)]">{subtitle}</p>
            )}
        </div>
    );
}

function DeltaBadge({ value, label }: { value: number; label: string }) {
    const isPositive = value >= 0;
    return (
        <div className="border border-[var(--color-border)] p-5">
            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                {label}
            </p>
            <p className={`mt-2 font-serif text-2xl ${isPositive ? 'text-green-700' : 'text-red-700'}`}>
                {isPositive ? '+' : ''}{value}
            </p>
        </div>
    );
}

export default function Dashboard({
    kpis,
    assessmentVolume,
    surveyAnalysis,
    domainDistribution,
    recentAssessments,
    orgUsage,
}: Props) {
    return (
        <AdminLayout title="Dashboard">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Platform Overview</h1>

            {/* KPI Cards */}
            <div className="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <KpiCard label="Total Users" value={kpis.total_users} subtitle={`+${kpis.new_users_30d} last 30d`} />
                <KpiCard label="Active Orgs" value={kpis.active_orgs} />
                <KpiCard label="Assessments" value={kpis.total_assessments} />
                <KpiCard label="Completed" value={kpis.completed_assessments} />
                <KpiCard label="Completion Rate" value={`${kpis.completion_rate}%`} />
                <DeltaBadge label="Avg Clarity Change" value={surveyAnalysis.avg_clarity_delta} />
            </div>

            {/* Assessment Volume Chart */}
            <div className="mt-12">
                <h2 className="text-sm uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Assessment Volume
                </h2>
                <div className="mt-4 h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={assessmentVolume}>
                            <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} />
                            <XAxis dataKey="period" tick={{ fontSize: 11 }} stroke={CHART_COLORS.secondary} />
                            <YAxis tick={{ fontSize: 11 }} stroke={CHART_COLORS.secondary} />
                            <Tooltip />
                            <Area
                                type="monotone"
                                dataKey="started"
                                stackId="1"
                                stroke={CHART_COLORS.secondary}
                                fill={CHART_COLORS.grid}
                                name="Started"
                            />
                            <Area
                                type="monotone"
                                dataKey="completed"
                                stackId="2"
                                stroke={CHART_COLORS.primary}
                                fill={CHART_COLORS.primary}
                                fillOpacity={0.3}
                                name="Completed"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </div>

            <div className="mt-12 grid grid-cols-1 gap-8 lg:grid-cols-2">
                {/* Domain Distribution */}
                <div>
                    <h2 className="text-sm uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Top Vocational Domains
                    </h2>
                    <div className="mt-4 h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={domainDistribution} layout="vertical">
                                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} />
                                <XAxis type="number" tick={{ fontSize: 11 }} stroke={CHART_COLORS.secondary} />
                                <YAxis
                                    type="category"
                                    dataKey="primary_domain"
                                    tick={{ fontSize: 10 }}
                                    width={120}
                                    stroke={CHART_COLORS.secondary}
                                />
                                <Tooltip />
                                <Bar dataKey="count" fill={CHART_COLORS.primary} name="Profiles" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Survey Scores */}
                <div>
                    <h2 className="text-sm uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Survey Impact ({surveyAnalysis.paired_count} paired)
                    </h2>
                    <div className="mt-4 grid grid-cols-2 gap-4">
                        <DeltaBadge label="Clarity Change" value={surveyAnalysis.avg_clarity_delta} />
                        <DeltaBadge label="Action Change" value={surveyAnalysis.avg_action_delta} />
                    </div>
                </div>
            </div>

            {/* Org Usage */}
            {orgUsage.length > 0 && (
                <div className="mt-12">
                    <h2 className="text-sm uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Organization Usage
                    </h2>
                    <table className="mt-4 w-full text-sm">
                        <thead>
                            <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                                <th className="pb-2">Organization</th>
                                <th className="pb-2">Members</th>
                                <th className="pb-2">Assessments</th>
                                <th className="pb-2">Usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orgUsage.map((org) => {
                                const pct = org.assessments_limit > 0
                                    ? Math.round((org.assessments_used / org.assessments_limit) * 100)
                                    : 0;
                                return (
                                    <tr key={org.id} className="border-b border-[var(--color-border)]">
                                        <td className="py-3">
                                            <Link href={`/admin/organizations/${org.id}`} className="text-[var(--color-text)] hover:underline">
                                                {org.name}
                                            </Link>
                                        </td>
                                        <td className="py-3 text-[var(--color-text-secondary)]">
                                            {org.member_count}/{org.member_limit}
                                        </td>
                                        <td className="py-3 text-[var(--color-text-secondary)]">
                                            {org.assessments_used}/{org.assessments_limit}
                                        </td>
                                        <td className="py-3">
                                            <div className="h-2 w-24 bg-[var(--color-surface)]">
                                                <div
                                                    className="h-full bg-[var(--color-text)]"
                                                    style={{ width: `${Math.min(pct, 100)}%` }}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Recent Assessments */}
            <div className="mt-12">
                <h2 className="text-sm uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Recent Assessments
                </h2>
                <table className="mt-4 w-full text-sm">
                    <thead>
                        <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                            <th className="pb-2">User</th>
                            <th className="pb-2">Org</th>
                            <th className="pb-2">Mode</th>
                            <th className="pb-2">Status</th>
                            <th className="pb-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recentAssessments.map((a) => (
                            <tr key={a.id} className="border-b border-[var(--color-border)]">
                                <td className="py-3 text-[var(--color-text)]">
                                    {a.user?.name ?? 'Guest'}
                                </td>
                                <td className="py-3 text-[var(--color-text-secondary)]">
                                    {a.organization ?? '—'}
                                </td>
                                <td className="py-3 text-[var(--color-text-secondary)]">{a.mode}</td>
                                <td className="py-3">
                                    <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                        {a.status}
                                    </span>
                                </td>
                                <td className="py-3 text-[var(--color-text-secondary)]">
                                    {new Date(a.created_at).toLocaleDateString()}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
