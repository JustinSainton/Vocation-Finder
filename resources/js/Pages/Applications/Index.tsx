import AppLayout from '../../Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

interface Application {
    id: string;
    status: string;
    company_name: string;
    job_title: string;
    job_url: string | null;
    priority: string;
    applied_at: string | null;
    next_action: string | null;
    next_action_date: string | null;
    updated_at: string;
    job_listing: { id: string; title: string; company_name: string } | null;
}

interface Analytics {
    funnel: Record<string, number>;
    conversion_rates: Record<string, number>;
    avg_response_days: number | null;
    ghosted_rate: number;
    weekly_velocity: number;
    total_applications: number;
}

interface Props {
    applications: {
        data: Application[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    analytics: Analytics;
    currentFilter: string | null;
}

const STATUS_LABELS: Record<string, string> = {
    saved: 'Saved',
    applied: 'Applied',
    phone_screen: 'Phone Screen',
    interviewing: 'Interviewing',
    offered: 'Offered',
    accepted: 'Accepted',
    rejected: 'Rejected',
    declined: 'Declined',
    withdrawn: 'Withdrawn',
    ghosted: 'Ghosted',
};

const STATUS_COLORS: Record<string, string> = {
    saved: 'bg-[var(--color-accent)] text-[var(--color-background)]',
    applied: 'bg-blue-100 text-blue-800',
    phone_screen: 'bg-indigo-100 text-indigo-800',
    interviewing: 'bg-purple-100 text-purple-800',
    offered: 'bg-green-100 text-green-800',
    accepted: 'bg-green-600 text-white',
    rejected: 'bg-red-100 text-red-700',
    declined: 'bg-orange-100 text-orange-700',
    withdrawn: 'bg-gray-100 text-gray-600',
    ghosted: 'bg-gray-200 text-gray-500',
};

const FILTER_STATUSES = ['saved', 'applied', 'phone_screen', 'interviewing', 'offered', 'accepted', 'rejected', 'ghosted'];

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function FunnelBar({ analytics }: { analytics: Analytics }) {
    const stages = ['saved', 'applied', 'interviewing', 'offered', 'accepted'];
    const max = Math.max(...stages.map((s) => analytics.funnel[s] ?? 0), 1);

    return (
        <div className="flex items-end gap-1">
            {stages.map((stage) => {
                const count = analytics.funnel[stage] ?? 0;
                const height = Math.max(4, (count / max) * 48);
                return (
                    <div key={stage} className="flex flex-col items-center gap-1">
                        <span className="text-xs text-[var(--color-text)]">{count}</span>
                        <div
                            className="w-10 bg-[var(--color-text)]"
                            style={{ height: `${height}px` }}
                        />
                        <span className="text-[10px] text-[var(--color-accent)]">
                            {stage === 'phone_screen' ? 'Screen' : STATUS_LABELS[stage]}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

export default function ApplicationsIndex({ applications, analytics, currentFilter }: Props) {
    const handleFilter = (status: string | null) => {
        router.get('/applications', status ? { status } : {}, { preserveState: true });
    };

    return (
        <AppLayout title="Applications">
            <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                Application Tracker
            </h1>

            {/* Analytics Summary */}
            {analytics.total_applications > 0 && (
                <div className="mt-6 border border-[var(--color-border)] p-5">
                    <div className="flex items-start justify-between gap-6">
                        <FunnelBar analytics={analytics} />
                        <div className="space-y-2 text-right">
                            <div>
                                <p className="text-xs text-[var(--color-accent)]">Weekly velocity</p>
                                <p className="text-lg text-[var(--color-text)]">{analytics.weekly_velocity}/wk</p>
                            </div>
                            {analytics.avg_response_days && (
                                <div>
                                    <p className="text-xs text-[var(--color-accent)]">Avg response</p>
                                    <p className="text-sm text-[var(--color-text)]">{analytics.avg_response_days} days</p>
                                </div>
                            )}
                            <div>
                                <p className="text-xs text-[var(--color-accent)]">Ghosted rate</p>
                                <p className="text-sm text-[var(--color-text)]">{Math.round(analytics.ghosted_rate * 100)}%</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Status Filters */}
            <div className="mt-6 flex flex-wrap gap-1">
                <button
                    onClick={() => handleFilter(null)}
                    className={`px-3 py-1 text-xs ${
                        !currentFilter
                            ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                            : 'border border-[var(--color-border)] text-[var(--color-text)]'
                    }`}
                >
                    All
                </button>
                {FILTER_STATUSES.map((s) => (
                    <button
                        key={s}
                        onClick={() => handleFilter(s)}
                        className={`px-3 py-1 text-xs ${
                            currentFilter === s
                                ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                                : 'border border-[var(--color-border)] text-[var(--color-text)]'
                        }`}
                    >
                        {STATUS_LABELS[s]}
                    </button>
                ))}
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Application List */}
            {applications.data.length === 0 ? (
                <div className="py-12 text-center">
                    <p className="font-serif text-lg text-[var(--color-text)]">
                        No applications tracked yet
                    </p>
                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                        Save a job from the Jobs page to start tracking your applications.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {applications.data.map((app) => (
                        <Link
                            key={app.id}
                            href={`/applications/${app.id}`}
                            className="block border border-[var(--color-border)] px-5 py-4 transition-colors hover:bg-[var(--color-surface)]"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-medium text-[var(--color-text)]">
                                            {app.job_title}
                                        </h3>
                                        <span className={`px-2 py-0.5 text-[10px] font-medium ${STATUS_COLORS[app.status] ?? ''}`}>
                                            {STATUS_LABELS[app.status] ?? app.status}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-[var(--color-text-secondary)]">
                                        {app.company_name}
                                        {app.applied_at && ` \u00B7 Applied ${formatDate(app.applied_at)}`}
                                    </p>
                                    {app.next_action && (
                                        <p className="mt-1 text-xs text-[var(--color-accent)]">
                                            Next: {app.next_action}
                                            {app.next_action_date && ` (${formatDate(app.next_action_date)})`}
                                        </p>
                                    )}
                                </div>
                                <span className="text-xs text-[var(--color-accent)]">
                                    {formatDate(app.updated_at)}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
