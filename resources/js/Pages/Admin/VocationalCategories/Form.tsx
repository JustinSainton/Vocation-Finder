import AdminLayout from '../../../Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface Props {
    category?: {
        id: string;
        name: string;
        description: string | null;
        ministry_connection: string | null;
        career_pathways: string[] | null;
        sort_order: number;
    };
}

export default function VocationalCategoryForm({ category }: Props) {
    const isEditing = !!category;

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        ministry_connection: category?.ministry_connection ?? '',
        career_pathways: category?.career_pathways ?? ([] as string[]),
        sort_order: category?.sort_order ?? 0,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/vocational-categories/${category!.id}`);
        } else {
            post('/admin/vocational-categories');
        }
    };

    const addPathway = () => {
        setData('career_pathways', [...data.career_pathways, '']);
    };

    const updatePathway = (index: number, value: string) => {
        const updated = [...data.career_pathways];
        updated[index] = value;
        setData('career_pathways', updated);
    };

    const removePathway = (index: number) => {
        setData('career_pathways', data.career_pathways.filter((_, i) => i !== index));
    };

    return (
        <AdminLayout title={isEditing ? 'Edit Category' : 'Create Category'}>
            <h1 className="font-serif text-2xl text-[var(--color-text)]">
                {isEditing ? 'Edit Vocational Category' : 'Create Vocational Category'}
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
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">Ministry Connection</label>
                    <textarea
                        value={data.ministry_connection}
                        onChange={(e) => setData('ministry_connection', e.target.value)}
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

                <div>
                    <div className="flex items-center justify-between">
                        <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                            Career Pathways
                        </label>
                        <button
                            type="button"
                            onClick={addPathway}
                            className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                        >
                            Add pathway
                        </button>
                    </div>
                    <div className="mt-2 space-y-2">
                        {data.career_pathways.map((pathway, i) => (
                            <div key={i} className="flex gap-2">
                                <input
                                    type="text"
                                    value={pathway}
                                    onChange={(e) => updatePathway(i, e.target.value)}
                                    className="flex-1 border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={() => removePathway(i)}
                                    className="px-2 text-xs text-red-600 hover:text-red-800"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                    </div>
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
