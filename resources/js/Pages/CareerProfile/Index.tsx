import AppLayout from '../../Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface WorkEntry {
    company: string;
    position: string;
    startDate: string;
    endDate: string;
    summary: string;
}

interface EducationEntry {
    institution: string;
    area: string;
    studyType: string;
    startDate: string;
    endDate: string;
}

interface SkillEntry {
    name: string;
    level: string;
}

interface CareerProfile {
    id: string;
    work_history: WorkEntry[] | null;
    education: EducationEntry[] | null;
    skills: SkillEntry[] | null;
    certifications: string[] | null;
    volunteer: WorkEntry[] | null;
    import_source: string | null;
    imported_at: string | null;
}

interface Props {
    profile: CareerProfile | null;
}

function Section({
    title,
    children,
    onAdd,
}: {
    title: string;
    children: React.ReactNode;
    onAdd?: () => void;
}) {
    return (
        <div>
            <div className="flex items-center justify-between">
                <h2 className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    {title}
                </h2>
                {onAdd && (
                    <button
                        onClick={onAdd}
                        className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                    >
                        Add
                    </button>
                )}
            </div>
            <div className="mt-3">{children}</div>
        </div>
    );
}

export default function CareerProfileIndex({ profile }: Props) {
    const [editing, setEditing] = useState(false);

    const { data, setData, put, processing } = useForm({
        work_history: profile?.work_history ?? [],
        education: profile?.education ?? [],
        skills: profile?.skills ?? [],
        certifications: profile?.certifications ?? [],
        volunteer: profile?.volunteer ?? [],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put('/career-profile', {
            onSuccess: () => setEditing(false),
        });
    };

    const addWorkEntry = () => {
        setData('work_history', [
            ...data.work_history,
            { company: '', position: '', startDate: '', endDate: '', summary: '' },
        ]);
        setEditing(true);
    };

    const addEducationEntry = () => {
        setData('education', [
            ...data.education,
            { institution: '', area: '', studyType: '', startDate: '', endDate: '' },
        ]);
        setEditing(true);
    };

    const addSkill = () => {
        setData('skills', [...data.skills, { name: '', level: '' }]);
        setEditing(true);
    };

    const hasContent = profile && (
        (profile.work_history?.length ?? 0) > 0 ||
        (profile.education?.length ?? 0) > 0 ||
        (profile.skills?.length ?? 0) > 0
    );

    return (
        <AppLayout title="Career Profile">
            <div className="flex items-center justify-between">
                <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                    Career Profile
                </h1>
                <div className="flex gap-3">
                    <Link
                        href="/career-profile/import"
                        className="border border-[var(--color-border)] px-4 py-2 text-xs tracking-wide text-[var(--color-text)]"
                    >
                        Import PDF
                    </Link>
                    {!editing && hasContent && (
                        <button
                            onClick={() => setEditing(true)}
                            className="bg-[var(--color-text)] px-4 py-2 text-xs tracking-wide text-[var(--color-background)]"
                        >
                            Edit
                        </button>
                    )}
                </div>
            </div>

            {profile?.import_source && (
                <p className="mt-2 text-xs text-[var(--color-text-secondary)]">
                    Imported from {profile.import_source.replace('_', ' ')}
                    {profile.imported_at && ` on ${new Date(profile.imported_at).toLocaleDateString()}`}
                </p>
            )}

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {!hasContent && !editing ? (
                <div className="py-12 text-center">
                    <p className="font-serif text-lg text-[var(--color-text)]">
                        Your career profile is empty
                    </p>
                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                        Import a resume PDF or add your experience manually to get started.
                    </p>
                    <div className="mt-6 flex justify-center gap-3">
                        <Link
                            href="/career-profile/import"
                            className="bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)]"
                        >
                            Import PDF
                        </Link>
                        <button
                            onClick={() => {
                                addWorkEntry();
                            }}
                            className="border border-[var(--color-border)] px-6 py-3 text-xs tracking-wide text-[var(--color-text)]"
                        >
                            Add Manually
                        </button>
                    </div>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-10">
                    <Section title="Work Experience" onAdd={editing ? addWorkEntry : undefined}>
                        {data.work_history.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-secondary)]">No work experience added yet.</p>
                        ) : (
                            <div className="space-y-4">
                                {data.work_history.map((entry, i) => (
                                    <div key={i} className="border border-[var(--color-border)] p-4">
                                        {editing ? (
                                            <div className="space-y-3">
                                                <div className="grid grid-cols-2 gap-3">
                                                    <input
                                                        value={entry.position}
                                                        onChange={(e) => {
                                                            const updated = [...data.work_history];
                                                            updated[i] = { ...entry, position: e.target.value };
                                                            setData('work_history', updated);
                                                        }}
                                                        placeholder="Position"
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                    <input
                                                        value={entry.company}
                                                        onChange={(e) => {
                                                            const updated = [...data.work_history];
                                                            updated[i] = { ...entry, company: e.target.value };
                                                            setData('work_history', updated);
                                                        }}
                                                        placeholder="Company"
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <input
                                                        type="month"
                                                        value={entry.startDate}
                                                        onChange={(e) => {
                                                            const updated = [...data.work_history];
                                                            updated[i] = { ...entry, startDate: e.target.value };
                                                            setData('work_history', updated);
                                                        }}
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                    <input
                                                        type="month"
                                                        value={entry.endDate}
                                                        onChange={(e) => {
                                                            const updated = [...data.work_history];
                                                            updated[i] = { ...entry, endDate: e.target.value };
                                                            setData('work_history', updated);
                                                        }}
                                                        placeholder="Present"
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                </div>
                                                <textarea
                                                    value={entry.summary}
                                                    onChange={(e) => {
                                                        const updated = [...data.work_history];
                                                        updated[i] = { ...entry, summary: e.target.value };
                                                        setData('work_history', updated);
                                                    }}
                                                    placeholder="Brief description of your role and accomplishments"
                                                    rows={3}
                                                    className="w-full border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData('work_history', data.work_history.filter((_, idx) => idx !== i));
                                                    }}
                                                    className="text-xs text-red-600 underline"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <p className="text-sm font-medium text-[var(--color-text)]">
                                                    {entry.position || 'Untitled Position'}
                                                </p>
                                                <p className="text-sm text-[var(--color-text-secondary)]">
                                                    {entry.company}
                                                    {entry.startDate && ` · ${entry.startDate}`}
                                                    {entry.endDate ? ` – ${entry.endDate}` : ' – Present'}
                                                </p>
                                                {entry.summary && (
                                                    <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                                                        {entry.summary}
                                                    </p>
                                                )}
                                            </>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>

                    <Section title="Education" onAdd={editing ? addEducationEntry : undefined}>
                        {data.education.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-secondary)]">No education added yet.</p>
                        ) : (
                            <div className="space-y-4">
                                {data.education.map((entry, i) => (
                                    <div key={i} className="border border-[var(--color-border)] p-4">
                                        {editing ? (
                                            <div className="space-y-3">
                                                <input
                                                    value={entry.institution}
                                                    onChange={(e) => {
                                                        const updated = [...data.education];
                                                        updated[i] = { ...entry, institution: e.target.value };
                                                        setData('education', updated);
                                                    }}
                                                    placeholder="School or Institution"
                                                    className="w-full border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                />
                                                <div className="grid grid-cols-2 gap-3">
                                                    <input
                                                        value={entry.studyType}
                                                        onChange={(e) => {
                                                            const updated = [...data.education];
                                                            updated[i] = { ...entry, studyType: e.target.value };
                                                            setData('education', updated);
                                                        }}
                                                        placeholder="Degree (e.g., B.S., M.A.)"
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                    <input
                                                        value={entry.area}
                                                        onChange={(e) => {
                                                            const updated = [...data.education];
                                                            updated[i] = { ...entry, area: e.target.value };
                                                            setData('education', updated);
                                                        }}
                                                        placeholder="Field of Study"
                                                        className="border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm text-[var(--color-text)]"
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData('education', data.education.filter((_, idx) => idx !== i));
                                                    }}
                                                    className="text-xs text-red-600 underline"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <p className="text-sm font-medium text-[var(--color-text)]">
                                                    {entry.institution || 'Untitled Institution'}
                                                </p>
                                                <p className="text-sm text-[var(--color-text-secondary)]">
                                                    {[entry.studyType, entry.area].filter(Boolean).join(' in ')}
                                                </p>
                                            </>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>

                    <Section title="Skills" onAdd={editing ? addSkill : undefined}>
                        {data.skills.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-secondary)]">No skills added yet.</p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {data.skills.map((skill, i) => (
                                    editing ? (
                                        <div key={i} className="flex items-center gap-1">
                                            <input
                                                value={skill.name}
                                                onChange={(e) => {
                                                    const updated = [...data.skills];
                                                    updated[i] = { ...skill, name: e.target.value };
                                                    setData('skills', updated);
                                                }}
                                                placeholder="Skill name"
                                                className="w-32 border border-[var(--color-border)] bg-transparent px-2 py-1 text-xs text-[var(--color-text)]"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('skills', data.skills.filter((_, idx) => idx !== i));
                                                }}
                                                className="text-xs text-red-600"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    ) : (
                                        <span
                                            key={i}
                                            className="border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-text)]"
                                        >
                                            {skill.name}
                                        </span>
                                    )
                                ))}
                            </div>
                        )}
                    </Section>

                    {editing && (
                        <div className="flex gap-3 pt-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)] disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Save Profile'}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setData({
                                        work_history: profile?.work_history ?? [],
                                        education: profile?.education ?? [],
                                        skills: profile?.skills ?? [],
                                        certifications: profile?.certifications ?? [],
                                        volunteer: profile?.volunteer ?? [],
                                    });
                                    setEditing(false);
                                }}
                                className="border border-[var(--color-border)] px-6 py-3 text-xs tracking-wide text-[var(--color-text)]"
                            >
                                Cancel
                            </button>
                        </div>
                    )}
                </form>
            )}
        </AppLayout>
    );
}
