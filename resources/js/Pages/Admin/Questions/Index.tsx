import AdminLayout from '../../../Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';

interface Question {
    id: string;
    question_text: string;
    category: { id: string; name: string } | null;
    sort_order: number;
}

interface Props {
    questions: {
        data: Question[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    sort: { by: string; dir: string };
}

export default function QuestionsIndex({ questions, sort }: Props) {
    const toggleSort = (column: string) => {
        const dir = sort.by === column && sort.dir === 'asc' ? 'desc' : 'asc';
        router.get('/admin/questions', { sort: column, dir }, { preserveState: true });
    };

    const handleDelete = (id: string) => {
        if (confirm('Delete this question?')) {
            router.delete(`/admin/questions/${id}`);
        }
    };

    return (
        <AdminLayout title="Questions">
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl text-[var(--color-text)]">Questions</h1>
                <Link
                    href="/admin/questions/create"
                    className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                >
                    Create
                </Link>
            </div>

            <table className="mt-6 w-full text-sm">
                <thead>
                    <tr className="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wider text-[var(--color-text-secondary)]">
                        <th
                            className="cursor-pointer pb-2"
                            onClick={() => toggleSort('sort_order')}
                        >
                            Order {sort.by === 'sort_order' ? (sort.dir === 'asc' ? '↑' : '↓') : ''}
                        </th>
                        <th className="pb-2">Question</th>
                        <th className="pb-2">Category</th>
                        <th className="pb-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {questions.data.map((q) => (
                        <tr key={q.id} className="border-b border-[var(--color-border)]">
                            <td className="py-3 text-[var(--color-text-secondary)]">{q.sort_order}</td>
                            <td className="py-3 text-[var(--color-text)]">
                                {q.question_text.length > 80 ? q.question_text.slice(0, 80) + '...' : q.question_text}
                            </td>
                            <td className="py-3 text-[var(--color-text-secondary)]">{q.category?.name ?? '-'}</td>
                            <td className="py-3">
                                <div className="flex gap-3">
                                    <Link
                                        href={`/admin/questions/${q.id}/edit`}
                                        className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(q.id)}
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

            <div className="mt-6 flex items-center gap-4 text-sm">
                {questions.prev_page_url && (
                    <Link href={questions.prev_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Previous
                    </Link>
                )}
                <span className="text-[var(--color-text-secondary)]">
                    Page {questions.current_page} of {questions.last_page}
                </span>
                {questions.next_page_url && (
                    <Link href={questions.next_page_url} className="text-[var(--color-text-secondary)] hover:text-[var(--color-text)]">
                        Next
                    </Link>
                )}
            </div>
        </AdminLayout>
    );
}
