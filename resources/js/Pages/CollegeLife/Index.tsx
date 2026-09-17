import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface Resource {
    kind: string;
    label: string;
    description: string;
    opener: string;
    links: { name: string; url: string }[];
}

interface Props {
    enrollment: { id: string; college_name: string; program: string | null; started_on: string | null } | null;
    resources: Resource[];
    syllabi: {
        id: string;
        course_code: string | null;
        course_title: string | null;
        term: string | null;
        parsed: boolean;
        kept: number;
        discarded: number;
    }[];
    coursework: { id: string; title: string; due_on: string | null; status: string }[];
    calendar_url: string;
}

const when = (date: string | null) =>
    date ? new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';

/**
 * Being in college.
 *
 * Two things are on this page and nothing else: the dates out of your own
 * syllabi, and the places on campus that exist for you. No progress bar, no
 * risk score, no "students like you". A student who is struggling in November
 * does not need a number telling them so.
 */
export default function CollegeLifeIndex({ enrollment, resources, syllabi, coursework, calendar_url }: Props) {
    const [copied, setCopied] = useState(false);
    const enrolling = useForm({ college_name: '', program: '', started_on: '' });
    const syllabus = useForm({ course_code: '', course_title: '', term: '', source_text: '' });

    return (
        <AppLayout title="College">
            <p className="type-eyebrow">Plan · College life</p>
            <h1 className="mt-2 font-serif text-3xl leading-tight text-[var(--color-text)]">
                {enrollment ? `You are at ${enrollment.college_name}` : 'Are you in college now?'}
            </h1>

            {!enrollment && (
                <form
                    className="mt-6 border-t border-[var(--color-divider)] pt-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        enrolling.post('/plan/college-life/enrollment', { preserveScroll: true });
                    }}
                >
                    <p className="text-[var(--color-text-secondary)]">
                        Tell us where, and this section fills up with your own coursework and what is on that campus.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <input
                            aria-label="Where you are enrolled"
                            required
                            value={enrolling.data.college_name}
                            onChange={(event) => enrolling.setData('college_name', event.target.value)}
                            placeholder="Where you are enrolled"
                            className="w-64 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                        />
                        <input
                            aria-label="What you are studying"
                            value={enrolling.data.program}
                            onChange={(event) => enrolling.setData('program', event.target.value)}
                            placeholder="What you are studying (if you know)"
                            className="w-64 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                        />
                        <button type="submit" className="border border-[var(--color-text)] px-4 py-1">
                            Save
                        </button>
                    </div>
                </form>
            )}

            {enrollment && (
                <>
                    <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                        <h2 className="type-eyebrow">Your coursework</h2>
                        <p className="mt-3 text-[var(--color-text-secondary)]">
                            Paste a syllabus and the dates in it join your plan. Anything we could not find printed in
                            the document is left out — check it against the syllabus, not against us.
                        </p>

                        <form
                            className="mt-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                syllabus.post('/plan/college-life/syllabus', {
                                    preserveScroll: true,
                                    onSuccess: () => syllabus.reset(),
                                });
                            }}
                        >
                            <div className="flex flex-wrap gap-3">
                                <input
                                    aria-label="Course code"
                                    value={syllabus.data.course_code}
                                    onChange={(event) => syllabus.setData('course_code', event.target.value)}
                                    placeholder="ENG 101"
                                    className="w-32 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                                />
                                <input
                                    aria-label="Course title"
                                    value={syllabus.data.course_title}
                                    onChange={(event) => syllabus.setData('course_title', event.target.value)}
                                    placeholder="Course title"
                                    className="w-64 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                                />
                            </div>
                            <textarea
                                aria-label="Syllabus text"
                                required
                                rows={6}
                                value={syllabus.data.source_text}
                                onChange={(event) => syllabus.setData('source_text', event.target.value)}
                                placeholder="Paste the syllabus here"
                                className="mt-3 w-full border border-[var(--color-divider)] bg-transparent px-2 py-1"
                            />
                            <button type="submit" className="mt-3 border border-[var(--color-text)] px-4 py-1">
                                Read this syllabus
                            </button>
                        </form>

                        {syllabi.length > 0 && (
                            <ul className="mt-6 divide-y divide-[var(--color-divider)]">
                                {syllabi.map((item) => (
                                    <li key={item.id} className="py-3">
                                        <p className="text-[var(--color-text)]">
                                            {item.course_code ?? item.course_title ?? 'Syllabus'}
                                        </p>
                                        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                                            {!item.parsed
                                                ? 'Reading it now.'
                                                : `${item.kept} dates added.${
                                                      item.discarded > 0
                                                          ? ` ${item.discarded} we could not read — check those yourself.`
                                                          : ''
                                                  }`}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {coursework.length > 0 && (
                            <ul className="mt-6 divide-y divide-[var(--color-divider)]">
                                {coursework.map((item) => (
                                    <li key={item.id} className="flex justify-between gap-4 py-3">
                                        <span className="text-[var(--color-text)]">{item.title}</span>
                                        <span className="type-eyebrow shrink-0">{when(item.due_on)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                        <h2 className="type-eyebrow">Your plan, on your calendar</h2>
                        <p className="mt-3 text-[var(--color-text-secondary)]">
                            Subscribe to this address in any calendar app and every date in your plan appears there. It
                            will not notify you; it just shows up where you already look.
                        </p>
                        <p className="mt-3 font-mono text-sm break-all text-[var(--color-text-secondary)]">
                            {calendar_url}
                        </p>
                        <div className="mt-3 flex gap-3">
                            <button
                                type="button"
                                className="border border-[var(--color-text)] px-4 py-1"
                                onClick={() => {
                                    void navigator.clipboard?.writeText(calendar_url);
                                    setCopied(true);
                                }}
                            >
                                {copied ? 'Copied' : 'Copy link'}
                            </button>
                            <button
                                type="button"
                                className="border border-[var(--color-divider)] px-4 py-1 text-[var(--color-text-secondary)]"
                                onClick={() => router.post('/plan/calendar/rotate', {}, { preserveScroll: true })}
                            >
                                Break this link
                            </button>
                        </div>
                    </section>
                </>
            )}

            <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                <h2 className="type-eyebrow">What is on every campus</h2>
                <p className="mt-3 text-[var(--color-text-secondary)]">
                    All of this is free and already paid for. The hard part is not finding the building — it is knowing
                    what to say when you walk in, so each one has a line you can use.
                </p>
                <ul className="mt-6 divide-y divide-[var(--color-divider)]">
                    {resources.map((resource) => (
                        <li key={resource.kind} className="py-5">
                            <h3 className="font-serif text-xl text-[var(--color-text)]">{resource.label}</h3>
                            <p className="mt-2 text-[var(--color-text-secondary)]">{resource.description}</p>
                            <p className="mt-2 text-[var(--color-text)]">{resource.opener}</p>
                            {resource.links.map((link) => (
                                <a
                                    key={link.url}
                                    href={link.url}
                                    className="mt-2 block text-sm underline"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {link.name}
                                </a>
                            ))}
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}
