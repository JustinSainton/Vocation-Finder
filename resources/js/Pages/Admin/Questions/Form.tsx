import AdminLayout from '../../../Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface Category {
    id: string;
    name: string;
}

interface Props {
    question?: {
        id: string;
        category_id: string;
        question_text: string;
        conversation_prompt: string | null;
        follow_up_prompts: string[] | null;
        sort_order: number;
    };
    categories: Category[];
}

export default function QuestionForm({ question, categories }: Props) {
    const isEditing = !!question;

    const { data, setData, post, put, processing, errors } = useForm({
        category_id: question?.category_id ?? '',
        question_text: question?.question_text ?? '',
        conversation_prompt: question?.conversation_prompt ?? '',
        follow_up_prompts: question?.follow_up_prompts ?? ([] as string[]),
        sort_order: question?.sort_order ?? 0,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/questions/${question!.id}`);
        } else {
            post('/admin/questions');
        }
    };

    const addFollowUp = () => {
        setData('follow_up_prompts', [...data.follow_up_prompts, '']);
    };

    const updateFollowUp = (index: number, value: string) => {
        const updated = [...data.follow_up_prompts];
        updated[index] = value;
        setData('follow_up_prompts', updated);
    };

    const removeFollowUp = (index: number) => {
        setData('follow_up_prompts', data.follow_up_prompts.filter((_, i) => i !== index));
    };

    return (
        <AdminLayout title={isEditing ? 'Edit Question' : 'Create Question'}>
            <h1 className="font-serif text-2xl text-[var(--color-text)]">
                {isEditing ? 'Edit Question' : 'Create Question'}
            </h1>

            <form onSubmit={handleSubmit} className="mt-8 space-y-6">
                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Category
                    </label>
                    <select
                        value={data.category_id}
                        onChange={(e) => setData('category_id', e.target.value)}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    >
                        <option value="">Select category...</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                    {errors.category_id && <p className="mt-1 text-xs text-red-600">{errors.category_id}</p>}
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Question Text
                    </label>
                    <textarea
                        value={data.question_text}
                        onChange={(e) => setData('question_text', e.target.value)}
                        rows={4}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                    {errors.question_text && <p className="mt-1 text-xs text-red-600">{errors.question_text}</p>}
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Conversation Prompt
                    </label>
                    <textarea
                        value={data.conversation_prompt}
                        onChange={(e) => setData('conversation_prompt', e.target.value)}
                        rows={3}
                        className="mt-1 w-full border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                    />
                </div>

                <div>
                    <label className="block text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Sort Order
                    </label>
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
                            Follow-up Prompts
                        </label>
                        <button
                            type="button"
                            onClick={addFollowUp}
                            className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                        >
                            Add prompt
                        </button>
                    </div>
                    <div className="mt-2 space-y-2">
                        {data.follow_up_prompts.map((prompt, i) => (
                            <div key={i} className="flex gap-2">
                                <input
                                    type="text"
                                    value={prompt}
                                    onChange={(e) => updateFollowUp(i, e.target.value)}
                                    className="flex-1 border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2 text-sm text-[var(--color-text)] focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={() => removeFollowUp(i)}
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
                    {isEditing ? 'Save changes' : 'Create question'}
                </button>
            </form>
        </AdminLayout>
    );
}
