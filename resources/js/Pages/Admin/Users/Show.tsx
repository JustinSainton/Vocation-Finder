import AdminLayout from '../../../Layouts/AdminLayout';
import { Link } from '@inertiajs/react';

interface UserData {
    id: string;
    name: string;
    email: string;
    role: string;
    created_at: string;
    assessment_credits: number;
}

interface Assessment {
    id: string;
    mode: string;
    status: string;
    created_at: string;
}

interface Props {
    user: UserData;
    assessments: Assessment[];
}

export default function UserShow({ user, assessments }: Props) {
    return (
        <AdminLayout title={user.name}>
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">{user.name}</h1>
                <Link
                    href={`/admin/users/${user.id}/edit`}
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Edit
                </Link>
            </div>

            <div className="mt-6 space-y-2 text-sm">
                <p className="text-[var(--color-text-secondary)]">
                    Email: <span className="text-[var(--color-text)]">{user.email}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Role: <span className="text-[var(--color-text)]">{user.role}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Credits: <span className="text-[var(--color-text)]">{user.assessment_credits}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Joined: <span className="text-[var(--color-text)]">{user.created_at}</span>
                </p>
            </div>

            <div className="mt-10">
                <h2 className="text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Assessments
                </h2>
                {assessments.length === 0 ? (
                    <p className="mt-4 text-sm text-[var(--color-text-secondary)]">No assessments yet.</p>
                ) : (
                    <table className="mt-4 w-full text-sm">
                        <thead>
                            <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                                <th className="pb-2">Mode</th>
                                <th className="pb-2">Status</th>
                                <th className="pb-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assessments.map((a) => (
                                <tr key={a.id} className="border-b border-[var(--color-border)]">
                                    <td className="py-3 text-[var(--color-text)]">{a.mode}</td>
                                    <td className="py-3">
                                        <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                                            {a.status}
                                        </span>
                                    </td>
                                    <td className="py-3 text-[var(--color-text-secondary)]">{a.created_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    );
}
