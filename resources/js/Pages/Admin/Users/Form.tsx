import AdminLayout from '../../../Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface Props {
    user: {
        id: string;
        name: string;
        email: string;
        role: string;
    };
}

export default function UserForm({ user }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        role: user.role,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    return (
        <AdminLayout title="Edit User">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Edit User</h1>

            <form onSubmit={handleSubmit} className="mt-8 space-y-6">
                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Name
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Email
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Role
                    </label>
                    <select
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value)}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    >
                        <option value="individual">Individual</option>
                        <option value="admin">Admin</option>
                        <option value="org_admin">Org Admin</option>
                    </select>
                    {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="bg-[var(--color-text)] px-6 py-2 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:opacity-50"
                >
                    Save changes
                </button>
            </form>
        </AdminLayout>
    );
}
