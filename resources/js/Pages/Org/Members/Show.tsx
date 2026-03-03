import OrgLayout from '../../../Layouts/OrgLayout';
import { Link } from '@inertiajs/react';

interface OrgRef {
    id: string;
    name: string;
    slug: string;
}

interface MemberDetail {
    id: string;
    name: string;
    email: string;
}

interface Assessment {
    id: string;
    status: string;
    mode: string;
    created_at: string;
    completed_at: string | null;
    primary_domain: string | null;
    mode_of_work: string | null;
}

interface Props {
    organization: OrgRef;
    member: MemberDetail;
    assessments: Assessment[];
}

export default function OrgMemberShow({ organization, member, assessments }: Props) {
    return (
        <OrgLayout title={member.name} orgName={organization.name} orgSlug={organization.slug}>
            <Link
                href={`/org/${organization.slug}/members`}
                className="mb-6 inline-block font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
            >
                &larr; All members
            </Link>

            <h1 className="mb-1 font-serif text-2xl text-[var(--color-text)]">{member.name}</h1>
            <p className="mb-8 font-sans text-sm text-[var(--color-accent)]">{member.email}</p>

            <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Assessments
            </p>

            {assessments.length === 0 ? (
                <p className="py-8 text-center text-[var(--color-text-secondary)]">
                    No assessments yet.
                </p>
            ) : (
                <div className="space-y-4">
                    {assessments.map((assessment) => (
                        <div
                            key={assessment.id}
                            className="border border-[var(--color-divider)] bg-[var(--color-surface)] p-5"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="font-serif text-[var(--color-text)]">
                                        {assessment.mode === 'written'
                                            ? 'Written Assessment'
                                            : 'Conversation Assessment'}
                                    </p>
                                    <p className="mt-1 font-sans text-sm text-[var(--color-text-secondary)]">
                                        Started {assessment.created_at}
                                        {assessment.completed_at &&
                                            ` — Completed ${assessment.completed_at}`}
                                    </p>
                                </div>
                                <span
                                    className={`font-sans text-xs uppercase tracking-wider ${
                                        assessment.status === 'completed'
                                            ? 'text-[var(--color-stone-700)]'
                                            : 'text-[var(--color-stone-400)]'
                                    }`}
                                >
                                    {assessment.status}
                                </span>
                            </div>

                            {assessment.status === 'completed' && (
                                <div className="mt-4 flex gap-6">
                                    {assessment.primary_domain && (
                                        <div>
                                            <p className="font-sans text-xs text-[var(--color-accent)]">
                                                Primary Domain
                                            </p>
                                            <p className="mt-1 text-sm text-[var(--color-text)]">
                                                {assessment.primary_domain}
                                            </p>
                                        </div>
                                    )}
                                    {assessment.mode_of_work && (
                                        <div>
                                            <p className="font-sans text-xs text-[var(--color-accent)]">
                                                Mode of Work
                                            </p>
                                            <p className="mt-1 text-sm text-[var(--color-text)]">
                                                {assessment.mode_of_work}
                                            </p>
                                        </div>
                                    )}
                                    <Link
                                        href={`/assessment/${assessment.id}/results`}
                                        className="ml-auto self-end font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                                    >
                                        View results &rarr;
                                    </Link>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </OrgLayout>
    );
}
