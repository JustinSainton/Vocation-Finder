import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    parentName: string;
    studentName: string;
    granted: boolean;
    token: string;
}

/**
 * A parent saying yes, on a link, without making an account.
 *
 * The page states the privacy boundary plainly rather than burying it,
 * because a parent who expects to read the conversations and then cannot will
 * feel misled — and a student who thinks their parent might be reading will
 * not be honest with the coach, which is what the whole thing runs on.
 */
export default function ParentConsentShow() {
    const { parentName, studentName, granted, token, status } = usePage().props as unknown as Props & {
        status?: string;
    };
    const form = useForm({});

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/consent/${token}`, { preserveScroll: true });
    };

    return (
        <div className="mx-auto max-w-[640px] px-4 py-24">
            <p className="text-sm text-stone-500">For {parentName}</p>

            <h1 className="mt-4 font-serif text-3xl leading-snug text-stone-900">
                {studentName} would like to start working with a vocational coach.
            </h1>

            <div className="mt-10 space-y-5 text-stone-700">
                <p>
                    The coach reads what {studentName} wrote in their assessment and helps them find one
                    concrete thing to do next. Not a list of careers — one step.
                </p>
                <p>
                    You will see their milestones: what they have finished, what they are working on now, and
                    one thing you could ask them about. You will not see their conversations with the coach or
                    what they write about themselves. That part is theirs.
                </p>
                <p className="text-stone-500">
                    You can withdraw this at any time. Nothing {studentName} has written is ever deleted.
                </p>
            </div>

            {status && <p className="mt-8 border-l-2 border-stone-300 pl-4 text-stone-700">{status}</p>}

            {granted ? (
                <p className="mt-10 text-stone-900">You have already said yes. Nothing else is needed.</p>
            ) : (
                <form onSubmit={submit} className="mt-10">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="border border-stone-900 px-6 py-3 text-sm text-stone-900 hover:bg-stone-900 hover:text-stone-50 disabled:opacity-50"
                    >
                        Yes, {studentName} can start
                    </button>
                </form>
            )}
        </div>
    );
}
