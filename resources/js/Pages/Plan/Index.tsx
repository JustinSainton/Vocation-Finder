import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface MilestoneView {
    id: string | null;
    kind: string;
    title: string;
    why: string | null;
    due_on: string;
    status: string;
    settled: boolean;
    is_yours_to_do: boolean;
    window: string | null;
}

interface Section {
    key: string;
    label: string;
    starts_on: string;
    ends_on: string | null;
    is_now: boolean;
    milestones: MilestoneView[];
}

interface Props {
    blurb: string;
    horizon: string;
    sections: Section[];
    action: { id: string; title: string; rationale: string | null } | null;
}

/**
 * The plan: a life in sections, four years at a glance.
 *
 * What is deliberately absent is as load-bearing as what is here. No counts,
 * no "3 of 7", no bar, no percentage — a plan that scores itself becomes a
 * report card, and a report card is something a parent reads rather than
 * something a student uses. The one action sits at the top and is the only
 * thing on this page that asks to be done; every milestone below it is a date,
 * not a task, which is what keeps a wide view from becoming a list of twenty
 * things.
 */
export default function PlanIndex({ blurb, horizon, sections, action }: Props) {
    const move = (milestone: MilestoneView, status: string) => {
        if (!milestone.id) {
            return;
        }

        router.patch(
            `/milestones/${milestone.id}`,
            { status },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AppLayout title="Plan">
            <p className="type-eyebrow">Plan</p>
            <p className="mt-2 font-serif text-xl leading-relaxed text-[var(--color-text)]">{blurb}</p>
            <p className="mt-2 text-[var(--color-text-secondary)]">{horizon}</p>

            {/*
              * The same one action the coach shows, read from the same queue.
              * A second list of next steps would be a second opinion about
              * what matters most, and the student would have to arbitrate it.
              */}
            {action && (
                <section className="mt-10 border-t border-b border-[var(--color-divider)] py-6">
                    <p className="type-eyebrow">What you are doing now</p>
                    <p className="mt-2 font-serif text-xl leading-snug text-[var(--color-text)]">{action.title}</p>
                    {action.rationale && (
                        <p className="mt-2 text-[var(--color-text-secondary)]">{action.rationale}</p>
                    )}
                </section>
            )}

            <div className="mt-12 space-y-12">
                {sections.map((section) => (
                    <section key={section.key} className="border-t border-[var(--color-divider)] pt-6">
                        <p className="type-eyebrow">
                            {section.label}
                            {section.is_now ? ' · you are here' : ''}
                        </p>

                        {section.milestones.length === 0 ? (
                            <p className="mt-3 text-[var(--color-text-secondary)]">
                                Nothing dated in here yet.
                            </p>
                        ) : (
                            <ul className="mt-4 space-y-8">
                                {section.milestones.map((milestone, index) => (
                                    <li key={milestone.id ?? `${section.key}-${index}`}>
                                        <p
                                            className={`font-serif text-lg leading-snug ${
                                                milestone.settled
                                                    ? 'text-[var(--color-muted)]'
                                                    : 'text-[var(--color-text)]'
                                            }`}
                                        >
                                            {milestone.title}
                                        </p>
                                        <p className="type-meta mt-1">
                                            {milestone.due_on} · {milestone.kind} · {milestone.status}
                                        </p>
                                        {milestone.why && (
                                            <p className="mt-2 text-[var(--color-text-secondary)]">{milestone.why}</p>
                                        )}
                                        {milestone.window && (
                                            <p className="mt-2 text-[var(--color-text-secondary)]">
                                                {milestone.window}
                                            </p>
                                        )}

                                        {/*
                                          * Only the things that are actually
                                          * theirs to do can be moved. Finishing
                                          * a year happens whether or not anyone
                                          * ticks it, so it carries no buttons —
                                          * nobody should be able to be behind
                                          * on the passage of time.
                                          */}
                                        {milestone.is_yours_to_do && !milestone.settled && (
                                            <div className="mt-3 flex flex-wrap gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => move(milestone, 'underway')}
                                                    className="border border-[var(--color-divider)] px-4 py-2 font-sans text-sm text-[var(--color-text)] hover:border-[var(--color-text)]"
                                                >
                                                    Started it
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => move(milestone, 'done')}
                                                    className="border border-[var(--color-divider)] px-4 py-2 font-sans text-sm text-[var(--color-text)] hover:border-[var(--color-text)]"
                                                >
                                                    Done
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => move(milestone, 'put_down')}
                                                    className="border border-[var(--color-divider)] px-4 py-2 font-sans text-sm text-[var(--color-text)] hover:border-[var(--color-text)]"
                                                >
                                                    Not doing this
                                                </button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ))}
            </div>
        </AppLayout>
    );
}
