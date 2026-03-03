import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';

interface Category {
    id: string;
    name: string;
    slug: string;
    sort_order: number;
}

interface Props {
    categories: Category[];
}

export default function VocationalCategoriesIndex({ categories }: Props) {
    const handleDelete = (id: string) => {
        if (confirm('Delete this category?')) {
            router.delete(`/admin/vocational-categories/${id}`);
        }
    };

    return (
        <AdminLayout title="Vocational Categories">
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">Vocational Categories</h1>
                <Link
                    href="/admin/vocational-categories/create"
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Create
                </Link>
            </div>

            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th className="pb-2">Order</th>
                        <th className="pb-2">Name</th>
                        <th className="pb-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {categories.map((cat) => (
                        <tr key={cat.id} className="border-b border-[var(--color-border)]">
                            <td className="py-3 text-[var(--color-text-secondary)]">{cat.sort_order}</td>
                            <td className="py-3 text-[var(--color-text)]">{cat.name}</td>
                            <td className="py-3">
                                <div className="flex gap-3">
                                    <Link
                                        href={`/admin/vocational-categories/${cat.id}/edit`}
                                        className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(cat.id)}
                                        className="text-xs text-red-600 underline hover:text-red-800"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </AdminLayout>
    );
}
