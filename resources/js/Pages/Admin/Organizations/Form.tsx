import AdminLayout from '../../../Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface Props {
    organization?: {
        id: string;
        name: string;
        type: string;
    };
}

export default function OrganizationForm({ organization }: Props) {
    const isEditing = !!organization;

    const { data, setData, post, put, processing, errors } = useForm({
        name: organization?.name ?? '',
        type: organization?.type ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/organizations/${organization!.id}`);
        } else {
            post('/admin/organizations');
        }
    };

    return (
        <AdminLayout title={isEditing ? 'Edit Organization' : 'Create Organization'}>
            <h1 className="font-serif text-2xl text-[var(--color-text)]">
                {isEditing ? 'Edit Organization' : 'Create Organization'}
            </h1>

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
                        Type
                    </label>
                    <input
                        type="text"
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        placeholder="e.g., church, school, nonprofit"
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] placeholder-[var(--color-text-secondary)] focus:outline-none"
                    />
                    {errors.type && <p className="mt-1 text-xs text-red-600">{errors.type}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="bg-[var(--color-text)] px-6 py-2 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:opacity-50"
                >
                    {isEditing ? 'Save changes' : 'Create organization'}
                </button>
            </form>
        </AdminLayout>
    );
}
