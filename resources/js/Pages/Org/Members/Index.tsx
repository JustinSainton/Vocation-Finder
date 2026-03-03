import { useState, FormEvent } from 'react';
import OrgLayout from '../../../Layouts/OrgLayout';
import { Link, router } from '@inertiajs/react';

interface OrgRef {
    id: string;
    name: string;
    slug: string;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
    assessments_count: number;
    joined_at: string | null;
}

interface Invitation {
    id: string;
    email: string;
    role: string;
    created_at: string;
}

interface Props {
    organization: OrgRef;
    members: Member[];
    pendingInvitations: Invitation[];
    memberLimit: number;
}

export default function OrgMembersIndex({
    organization,
    members,
    pendingInvitations,
    memberLimit,
}: Props) {
    const [showInvite, setShowInvite] = useState(false);
    const [email, setEmail] = useState('');
    const [role, setRole] = useState('member');
    const [submitting, setSubmitting] = useState(false);

    const handleInvite = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            `/org/${organization.slug}/members/invite`,
            { email, role },
            {
                onFinish: () => {
                    setSubmitting(false);
                    setEmail('');
                    setShowInvite(false);
                },
            }
        );
    };

    const handleRemove = (userId: string, name: string) => {
        if (confirm(`Remove ${name} from this organization?`)) {
            router.delete(`/org/${organization.slug}/members/${userId}`);
        }
    };

    const totalSlots = members.length + pendingInvitations.length;

    return (
        <OrgLayout title="Members" orgName={organization.name} orgSlug={organization.slug}>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="font-serif text-2xl text-[var(--color-text)]">Members</h1>
                    <p className="mt-1 font-sans text-sm text-[var(--color-accent)]">
                        {totalSlots} of {memberLimit} seats used
                    </p>
                </div>
                <button
                    onClick={() => setShowInvite(!showInvite)}
                    className="bg-[var(--color-text)] px-5 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                >
                    Invite member
                </button>
            </div>

            {/* Invite form */}
            {showInvite && (
                <form
                    onSubmit={handleInvite}
                    className="mb-8 border border-[var(--color-divider)] bg-[var(--color-surface)] p-5"
                >
                    <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Send Invitation
                    </p>
                    <div className="flex gap-3">
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="email@example.com"
                            required
                            className="flex-1 border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        />
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                            className="border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button
                            type="submit"
                            disabled={submitting}
                            className="bg-[var(--color-text)] px-5 py-2 font-sans text-sm text-[var(--color-background)] disabled:opacity-50"
                        >
                            {submitting ? 'Sending...' : 'Send'}
                        </button>
                    </div>
                </form>
            )}

            {/* Members table */}
            <table className="mb-10 w-full">
                <thead>
                    <tr className="border-b border-[var(--color-divider)] text-left">
                        <Th>Name</Th>
                        <Th>Email</Th>
                        <Th>Role</Th>
                        <Th>Assessments</Th>
                        <Th>Joined</Th>
                        <Th>Actions</Th>
                    </tr>
                </thead>
                <tbody>
                    {members.map((member) => (
                        <tr
                            key={member.id}
                            className="border-b border-[var(--color-divider)]"
                        >
                            <td className="py-3 pr-4 font-serif text-sm text-[var(--color-text)]">
                                {member.name}
                            </td>
                            <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text-secondary)]">
                                {member.email}
                            </td>
                            <td className="py-3 pr-4">
                                <span className="font-sans text-xs uppercase tracking-wider text-[var(--color-accent)]">
                                    {member.role}
                                </span>
                            </td>
                            <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text)]">
                                {member.assessments_count}
                            </td>
                            <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text-secondary)]">
                                {member.joined_at ?? '--'}
                            </td>
                            <td className="py-3">
                                <div className="flex gap-3">
                                    <Link
                                        href={`/org/${organization.slug}/members/${member.id}`}
                                        className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                                    >
                                        View
                                    </Link>
                                    <button
                                        onClick={() => handleRemove(member.id, member.name)}
                                        className="font-sans text-sm text-[var(--color-stone-400)] hover:text-[var(--color-text)]"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {/* Pending invitations */}
            {pendingInvitations.length > 0 && (
                <>
                    <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                        Pending Invitations
                    </p>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-[var(--color-divider)] text-left">
                                <Th>Email</Th>
                                <Th>Role</Th>
                                <Th>Sent</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {pendingInvitations.map((inv) => (
                                <tr
                                    key={inv.id}
                                    className="border-b border-[var(--color-divider)]"
                                >
                                    <td className="py-3 pr-4 font-sans text-sm text-[var(--color-text)]">
                                        {inv.email}
                                    </td>
                                    <td className="py-3 pr-4 font-sans text-xs uppercase tracking-wider text-[var(--color-accent)]">
                                        {inv.role}
                                    </td>
                                    <td className="py-3 font-sans text-sm text-[var(--color-text-secondary)]">
                                        {inv.created_at}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </>
            )}
        </OrgLayout>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return (
        <th className="pb-3 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
            {children}
        </th>
    );
}
