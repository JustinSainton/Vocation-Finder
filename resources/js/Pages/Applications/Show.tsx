import AppLayout from '../../Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Event {
    id: string;
    event_type: string;
    from_status: string | null;
    to_status: string | null;
    details: Record<string, any> | null;
    occurred_at: string;
}

interface ApplicationDetail {
    id: string;
    status: string;
    company_name: string;
    job_title: string;
    job_url: string | null;
    priority: string;
    applied_at: string | null;
    salary_offered: number | null;
    notes: string | null;
    source: string | null;
    contact_name: string | null;
    contact_email: string | null;
    next_action: string | null;
    next_action_date: string | null;
    created_at: string;
    events: Event[];
    job_listing: { id: string; title: string; company_name: string; source_url: string | null } | null;
}

interface Props {
    application: ApplicationDetail;
}

const STATUS_LABELS: Record<string, string> = {
    saved: 'Saved', applied: 'Applied', phone_screen: 'Phone Screen',
    interviewing: 'Interviewing', offered: 'Offered', accepted: 'Accepted',
    rejected: 'Rejected', declined: 'Declined', withdrawn: 'Withdrawn', ghosted: 'Ghosted',
};

const TRANSITIONS: Record<string, string[]> = {
    saved: ['applied', 'withdrawn'],
    applied: ['phone_screen', 'interviewing', 'rejected', 'withdrawn'],
    phone_screen: ['interviewing', 'rejected', 'withdrawn'],
    interviewing: ['offered', 'rejected', 'withdrawn'],
    offered: ['accepted', 'declined', 'withdrawn'],
    ghosted: ['interviewing'],
};

function formatDateTime(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

export default function ApplicationShow({ application }: Props) {
    const [updating, setUpdating] = useState(false);

    const handleStatusChange = async (newStatus: string) => {
        setUpdating(true);
        try {
            await fetch(`/api/v1/applications/${application.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ status: newStatus }),
            });
            router.reload();
        } finally {
            setUpdating(false);
        }
    };

    const availableTransitions = TRANSITIONS[application.status] ?? [];
    const isTerminal = ['accepted', 'rejected', 'declined', 'withdrawn'].includes(application.status);

    return (
        <AppLayout title={application.job_title}>
            <Link
                href="/applications"
                className="font-sans text-xs tracking-wide text-[var(--color-text-secondary)] hover:text-[var(--color-text)]"
            >
                &larr; Back to Applications
            </Link>

            <div className="mt-6">
                <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                    {application.job_title}
                </h1>
                <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                    {application.company_name}
                    {application.source && ` \u00B7 via ${application.source}`}
                </p>
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Status + Actions */}
            <div className="mb-8">
                <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    Status
                </h2>
                <p className="mt-2 text-sm font-medium text-[var(--color-text)]">
                    {STATUS_LABELS[application.status]}
                </p>

                {!isTerminal && availableTransitions.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {availableTransitions.map((status) => (
                            <button
                                key={status}
                                onClick={() => handleStatusChange(status)}
                                disabled={updating}
                                className="border border-[var(--color-border)] px-3 py-1.5 text-xs text-[var(--color-text)] transition-colors hover:bg-[var(--color-text)] hover:text-[var(--color-background)] disabled:opacity-50"
                            >
                                Move to {STATUS_LABELS[status]}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Details */}
            <div className="mb-8 grid grid-cols-2 gap-4">
                {application.applied_at && (
                    <div>
                        <p className="text-xs text-[var(--color-accent)]">Applied</p>
                        <p className="text-sm text-[var(--color-text)]">{formatDate(application.applied_at)}</p>
                    </div>
                )}
                <div>
                    <p className="text-xs text-[var(--color-accent)]">Priority</p>
                    <p className="text-sm capitalize text-[var(--color-text)]">{application.priority}</p>
                </div>
                {application.salary_offered && (
                    <div>
                        <p className="text-xs text-[var(--color-accent)]">Salary Offered</p>
                        <p className="text-sm text-[var(--color-text)]">${application.salary_offered.toLocaleString()}</p>
                    </div>
                )}
                {application.contact_name && (
                    <div>
                        <p className="text-xs text-[var(--color-accent)]">Contact</p>
                        <p className="text-sm text-[var(--color-text)]">
                            {application.contact_name}
                            {application.contact_email && ` (${application.contact_email})`}
                        </p>
                    </div>
                )}
                {application.next_action && (
                    <div className="col-span-2">
                        <p className="text-xs text-[var(--color-accent)]">Next Action</p>
                        <p className="text-sm text-[var(--color-text)]">
                            {application.next_action}
                            {application.next_action_date && ` \u2014 ${formatDate(application.next_action_date)}`}
                        </p>
                    </div>
                )}
            </div>

            {application.notes && (
                <div className="mb-8">
                    <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">Notes</h2>
                    <p className="mt-2 text-sm leading-relaxed text-[var(--color-text-secondary)]">{application.notes}</p>
                </div>
            )}

            {/* Actions */}
            <div className="mb-8 flex gap-3">
                {application.job_url && (
                    <a
                        href={application.job_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="border border-[var(--color-border)] px-4 py-2 text-xs tracking-wide text-[var(--color-text)]"
                    >
                        View Job Posting
                    </a>
                )}
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Event Timeline */}
            <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Activity Timeline
            </h2>
            <div className="mt-4 space-y-3">
                {application.events.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-secondary)]">No activity recorded yet.</p>
                ) : (
                    application.events.map((event) => (
                        <div key={event.id} className="flex gap-3">
                            <div className="mt-1.5 h-2 w-2 shrink-0 bg-[var(--color-accent)]" />
                            <div>
                                <p className="text-sm text-[var(--color-text)]">
                                    {event.event_type === 'status_change'
                                        ? `${STATUS_LABELS[event.from_status ?? ''] ?? event.from_status} \u2192 ${STATUS_LABELS[event.to_status ?? ''] ?? event.to_status}`
                                        : event.event_type.replace(/_/g, ' ')}
                                </p>
                                <p className="text-xs text-[var(--color-accent)]">
                                    {formatDateTime(event.occurred_at)}
                                </p>
                                {event.details?.reason && (
                                    <p className="text-xs text-[var(--color-text-secondary)]">{event.details.reason}</p>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
