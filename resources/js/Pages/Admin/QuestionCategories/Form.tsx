import AdminLayout from '../../../Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface Props {
    category?: {
        id: string;
        name: string;
        description: string | null;
        theological_basis: string | null;
        what_it_reveals: string | null;
        sort_order: number;
    };
}

export default function QuestionCategoryForm({ category }: Props) {
    const isEditing = !!category;

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        theological_basis: category?.theological_basis ?? '',
        what_it_reveals: category?.what_it_reveals ?? '',
        sort_order: category?.sort_order ?? 0,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/question-categories/${category!.id}`);
        } else {
            post('/admin/question-categories');
        }
    };

    return (
        <AdminLayout title={isEditing ? 'Edit Category' : 'Create Category'}>
            <h1 className="font-serif text-2xl text-[var(--color-text)]">
                {isEditing ? 'Edit Question Category' : 'Create Question Category'}
            </h1>

            <form onSubmit={handleSubmit} className="mt-8 space-y-6">
                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Name</label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Description</label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={3}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Theological Basis</label>
                    <textarea
                        value={data.theological_basis}
                        onChange={(e) => setData('theological_basis', e.target.value)}
                        rows={3}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">What It Reveals</label>
                    <textarea
                        value={data.what_it_reveals}
                        onChange={(e) => setData('what_it_reveals', e.target.value)}
                        rows={3}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Sort Order</label>
                    <input
                        type="number"
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                        className="mt-1 w-32 border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                    {errors.sort_order && <p className="mt-1 text-xs text-red-600">{errors.sort_order}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="bg-[var(--color-text)] px-6 py-2 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:opacity-50"
                >
                    {isEditing ? 'Save changes' : 'Create category'}
                </button>
            </form>
        </AdminLayout>
    );
}
