import { usePage } from '@inertiajs/react';

interface Milestone {
    title: string;
    kind: string;
    due_on: string;
    status: string;
}

interface Summary {
    reportable: boolean;
    reason?: string;
    student_name?: string;
    assessment_completed?: boolean;
    actions_completed?: number;
    gaps_closed?: number;
    current_action?: string | null;
    milestones?: Milestone[];
    suggested_conversation?: string;
}

interface Props {
    parent_name: string;
    summary: Summary;
}

/**
 * The parent's whole view of their child's work.
 *
 * Everything rendered here arrives already filtered by ParentVisibility. The
 * page never reaches for a model and never derives a second figure from the
 * ones it was handed — counting is how a progress page turns into a report
 * card, and the design forbids percentages, dials and scores outright.
 */
export default function FamilyReport() {
    const { parent_name, summary } = usePage().props as unknown as Props;

    if (!summary.reportable) {
        return (
            <div className="mx-auto max-w-[640px] px-4 py-24">
                <p className="text-sm text-stone-500">For {parent_name}</p>
                <p className="mt-6 text-stone-700">{summary.reason}</p>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-[640px] px-4 py-24">
            <p className="text-sm text-stone-500">For {parent_name}</p>

            <h1 className="mt-4 font-serif text-3xl leading-snug text-stone-900">
                How {summary.student_name} is going.
            </h1>

            <section className="mt-12">
                <p className="type-eyebrow text-stone-500">Working on now</p>
                <p className="mt-3 text-stone-900">
                    {summary.current_action ?? 'Nothing in progress this month.'}
                </p>
            </section>

            {summary.milestones && summary.milestones.length > 0 && (
                <section className="mt-12">
                    <p className="type-eyebrow text-stone-500">Dates coming up</p>
                    <ul className="mt-3 space-y-3">
                        {summary.milestones.map((milestone) => (
                            <li key={`${milestone.due_on}-${milestone.title}`} className="border-t border-stone-200 pt-3">
                                <p className="text-stone-900">{milestone.title}</p>
                                <p className="mt-1 text-sm text-stone-500">
                                    {milestone.due_on} · {milestone.kind} · {milestone.status}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mt-12">
                <p className="type-eyebrow text-stone-500">One thing you can do</p>
                <p className="mt-3 text-stone-900">{summary.suggested_conversation}</p>
            </section>

            <p className="mt-16 border-l-2 border-stone-300 pl-4 text-sm text-stone-500">
                We do not show you {summary.student_name}&rsquo;s conversations with the coach, or what they
                write about themselves. A student who thinks their parent is reading everything stops being
                honest with the coach, and the honesty is what the whole thing runs on.
            </p>
        </div>
    );
}
