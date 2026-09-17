import { Link, usePage } from '@inertiajs/react';
import OrgLayout from '../../Layouts/OrgLayout';

interface Student {
    id: string;
    name: string;
    detail: string | null;
}

interface Group {
    key: string;
    label: string;
    move: string;
    needs_somebody: boolean;
    students: Student[];
}

interface Props {
    organization: { id: string; name: string; slug: string };
    cohort: {
        seats: { used: number; pending: number; limit: number };
        counts: { students: number; started: number; steps_finished: number; obstacles_cleared: number };
        groups: Group[];
    };
}

/**
 * Deliberately not a leaderboard.
 *
 * The groups render in the order the server sent them, and the server puts
 * "Working" last. No column is sortable, nothing is coloured by standing, and
 * no student carries a figure — the only numbers on this page are counts of
 * the cohort, which is the reporting a school actually bought.
 */
export default function OrgCohort() {
    const { organization, cohort } = usePage().props as unknown as Props;

    return (
        <OrgLayout title="Cohort">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Who needs a person this week</h1>

            <dl className="mt-8 flex flex-wrap gap-x-12 gap-y-4 border-b border-[var(--color-divider)] pb-8">
                {[
                    ['Students', cohort.counts.students],
                    ['Have a portrait', cohort.counts.started],
                    ['Steps finished', cohort.counts.steps_finished],
                    ['Obstacles cleared', cohort.counts.obstacles_cleared],
                    ['Seats used', `${cohort.seats.used} of ${cohort.seats.limit}`],
                ].map(([label, value]) => (
                    <div key={String(label)}>
                        <dt className="type-eyebrow text-[var(--color-muted)]">{label}</dt>
                        <dd className="mt-1 font-serif text-xl text-[var(--color-text)]">{value}</dd>
                    </div>
                ))}
            </dl>

            {cohort.seats.pending > 0 && (
                <p className="mt-4 font-sans text-sm text-[var(--color-muted)]">
                    {cohort.seats.pending} invitation{cohort.seats.pending === 1 ? '' : 's'} still unaccepted.
                </p>
            )}

            {cohort.groups
                .filter((group) => group.students.length > 0)
                .map((group) => (
                    <section key={group.key} className="mt-12">
                        <h2 className="font-serif text-lg text-[var(--color-text)]">{group.label}</h2>
                        <p className="mt-1 max-w-[560px] font-sans text-sm text-[var(--color-muted)]">
                            {group.move}
                        </p>

                        <ul className="mt-4">
                            {group.students.map((student) => (
                                <li
                                    key={student.id}
                                    className="flex items-baseline justify-between border-t border-[var(--color-divider)] py-3"
                                >
                                    <Link
                                        href={`/org/${organization.slug}/members/${student.id}`}
                                        className="font-sans text-sm text-[var(--color-text)] underline-offset-4 hover:underline"
                                    >
                                        {student.name}
                                    </Link>
                                    {student.detail && (
                                        <span className="font-sans text-sm text-[var(--color-muted)]">
                                            {student.detail}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}

            <p className="mt-16 max-w-[560px] border-l-2 border-[var(--color-divider)] pl-4 font-sans text-sm text-[var(--color-muted)]">
                This page shows you who to talk to, not how anyone is doing. Coaching conversations and
                anything a student wrote about themselves are not reachable from here, for you or for
                anyone else with an account.
            </p>
        </OrgLayout>
    );
}
