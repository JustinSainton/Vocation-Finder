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

interface JobPlatformMetrics {
    jobs: { total: number; active: number; classified: number; by_source: Record<string, number>; ingested_7d: number };
    resumes: { total_generated: number; ready: number; avg_quality: number | null };
    cover_letters: { total_generated: number };
    application_funnel: { total: number; applied: number; interviewing: number; offered: number; accepted: number; ghosted: number };
    active_job_users_30d: number;
    feature_flags: Array<{ key: string; name: string; is_enabled: boolean }>;
}

interface CostTracking {
    expected: { total: number };
    estimated_actual: { total: number; breakdown: Record<string, number> };
    variance: { amount: number; percentage: number };
    usage: Record<string, number>;
}

interface Props {
    kpis: Kpis;
    assessmentVolume: VolumePoint[];
    surveyAnalysis: SurveyAnalysis;
    domainDistribution: DomainEntry[];
    recentAssessments: RecentAssessment[];
    orgUsage: OrgUsage[];
    jobPlatformMetrics?: JobPlatformMetrics;
    costTracking?: CostTracking;
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
    jobPlatformMetrics,
    costTracking,
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
                            <BarChart data={domainDistribution.map(d => ({ ...d, label: d.primary_domain?.split(/[—–\-:,.]/)?.[ 0]?.trim()?.slice(0, 30) ?? d.primary_domain }))} layout="vertical">
                                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} />
                                <XAxis type="number" tick={{ fontSize: 11 }} stroke={CHART_COLORS.secondary} />
                                <YAxis
                                    type="category"
                                    dataKey="label"
                                    tick={{ fontSize: 11 }}
                                    width={160}
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

            {/* Cost Tracking: Expected vs Actual */}
            {costTracking && (
                <div className="mt-10">
                    <h2 className="font-sans text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Cost Tracking — Expected vs. Actual
                    </h2>
                    <div className="mt-4 grid grid-cols-3 gap-4">
                        <div className="border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Expected Monthly</p>
                            <p className="mt-1 text-2xl font-light text-[var(--color-text)]">${costTracking.expected?.total?.toFixed(2) ?? '0.00'}</p>
                        </div>
                        <div className="border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Estimated Actual</p>
                            <p className="mt-1 text-2xl font-light text-[var(--color-text)]">${costTracking.estimated_actual?.total?.toFixed(2) ?? '0.00'}</p>
                        </div>
                        <div className="border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Variance</p>
                            <p className={`mt-1 text-2xl font-light ${(costTracking.variance?.amount ?? 0) > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                {(costTracking.variance?.amount ?? 0) > 0 ? '+' : ''}${costTracking.variance?.amount?.toFixed(2) ?? '0.00'}
                            </p>
                            <p className="text-xs text-[var(--color-text-secondary)]">
                                {costTracking.variance?.percentage !== undefined ? `${costTracking.variance.percentage > 0 ? '+' : ''}${costTracking.variance.percentage.toFixed(0)}%` : ''}
                            </p>
                        </div>
                    </div>
                    {costTracking.usage && Object.keys(costTracking.usage).length > 0 && (
                        <div className="mt-4 border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Usage This Month</p>
                            <div className="mt-3 grid grid-cols-4 gap-4">
                                {Object.entries(costTracking.usage).map(([key, value]) => (
                                    <div key={key}>
                                        <p className="text-xs text-[var(--color-text-secondary)]">{key.replace(/_/g, ' ')}</p>
                                        <p className="text-lg text-[var(--color-text)]">{value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Job Platform Metrics */}
            {jobPlatformMetrics && (
                <div className="mt-10">
                    <h2 className="font-sans text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Job Platform
                    </h2>
                    <div className="mt-4 grid grid-cols-4 gap-4">
                        <KpiCard label="Total Jobs" value={jobPlatformMetrics.jobs.total} subtitle={`${jobPlatformMetrics.jobs.active} active`} />
                        <KpiCard label="Ingested (7d)" value={jobPlatformMetrics.jobs.ingested_7d} />
                        <KpiCard label="Resumes Generated" value={jobPlatformMetrics.resumes.total_generated} subtitle={jobPlatformMetrics.resumes.avg_quality ? `Avg quality: ${jobPlatformMetrics.resumes.avg_quality}` : undefined} />
                        <KpiCard label="Cover Letters" value={jobPlatformMetrics.cover_letters.total_generated} />
                    </div>

                    {/* Application Funnel */}
                    <div className="mt-4 border border-[var(--color-border)] p-5">
                        <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Application Funnel (Platform-Wide)</p>
                        <div className="mt-3 flex items-end gap-2">
                            {(['total', 'applied', 'interviewing', 'offered', 'accepted'] as const).map((stage) => {
                                const count = jobPlatformMetrics.application_funnel[stage] ?? 0;
                                const max = Math.max(jobPlatformMetrics.application_funnel.total, 1);
                                const height = Math.max(4, (count / max) * 64);
                                return (
                                    <div key={stage} className="flex flex-col items-center gap-1">
                                        <span className="text-xs text-[var(--color-text)]">{count}</span>
                                        <div className="w-14 bg-[var(--color-text)]" style={{ height: `${height}px` }} />
                                        <span className="text-[10px] capitalize text-[var(--color-accent)]">{stage}</span>
                                    </div>
                                );
                            })}
                            {jobPlatformMetrics.application_funnel.ghosted > 0 && (
                                <div className="flex flex-col items-center gap-1 ml-4">
                                    <span className="text-xs text-red-600">{jobPlatformMetrics.application_funnel.ghosted}</span>
                                    <div className="w-14 bg-red-200" style={{ height: `${Math.max(4, (jobPlatformMetrics.application_funnel.ghosted / Math.max(jobPlatformMetrics.application_funnel.total, 1)) * 64)}px` }} />
                                    <span className="text-[10px] text-red-400">ghosted</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Job Sources */}
                    {Object.keys(jobPlatformMetrics.jobs.by_source).length > 0 && (
                        <div className="mt-4 border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Jobs by Source</p>
                            <div className="mt-3 flex gap-6">
                                {Object.entries(jobPlatformMetrics.jobs.by_source).map(([source, count]) => (
                                    <div key={source}>
                                        <p className="text-xs capitalize text-[var(--color-text-secondary)]">{source}</p>
                                        <p className="text-lg text-[var(--color-text)]">{count}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Feature Flags Status */}
                    {jobPlatformMetrics.feature_flags.length > 0 && (
                        <div className="mt-4 border border-[var(--color-border)] p-5">
                            <p className="text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Feature Flags</p>
                            <div className="mt-3 flex flex-wrap gap-3">
                                {jobPlatformMetrics.feature_flags.map((flag) => (
                                    <span
                                        key={flag.key}
                                        className={`px-3 py-1 text-xs ${flag.is_enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}`}
                                    >
                                        {flag.name}: {flag.is_enabled ? 'ON' : 'OFF'}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    <KpiCard label="Active Job Users (30d)" value={jobPlatformMetrics.active_job_users_30d} />
                </div>
            )}
        </AdminLayout>
    );
}
