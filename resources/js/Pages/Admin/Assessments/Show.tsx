import AdminLayout from '../../../Layouts/AdminLayout';

interface Answer {
    id: string;
    question_text: string;
    category_name: string | null;
    response_text: string | null;
}

interface Props {
    assessment: {
        id: string;
        user: { id: string; name: string; email: string } | null;
        mode: string;
        status: string;
        created_at: string;
        completed_at: string | null;
    };
    answers: Answer[];
    profile: {
        opening_synthesis: string | null;
        vocational_orientation: string | null;
        primary_pathways: string[] | null;
        specific_considerations: string | null;
        next_steps: string[] | null;
        primary_domain: string | null;
        mode_of_work: string | null;
    } | null;
}

export default function AssessmentShow({ assessment, answers, profile }: Props) {
    // Group answers by category
    const grouped = answers.reduce<Record<string, Answer[]>>((acc, answer) => {
        const cat = answer.category_name ?? 'Uncategorized';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(answer);
        return acc;
    }, {});

    return (
        <AdminLayout title="Assessment Detail">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Assessment Detail</h1>

            <div className="mt-6 space-y-2 text-sm">
                <p className="text-[var(--color-text-secondary)]">
                    User: <span className="text-[var(--color-text)]">{assessment.user?.name ?? 'Guest'}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Mode: <span className="text-[var(--color-text)]">{assessment.mode}</span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Status:{' '}
                    <span className="bg-[var(--color-surface)] px-2 py-0.5 text-xs text-[var(--color-text-secondary)]">
                        {assessment.status}
                    </span>
                </p>
                <p className="text-[var(--color-text-secondary)]">
                    Started: <span className="text-[var(--color-text)]">{assessment.created_at}</span>
                </p>
                {assessment.completed_at && (
                    <p className="text-[var(--color-text-secondary)]">
                        Completed: <span className="text-[var(--color-text)]">{assessment.completed_at}</span>
                    </p>
                )}
            </div>

            {/* Answers by category */}
            <div className="mt-10">
                <h2 className="text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                    Answers ({answers.length})
                </h2>
                {Object.entries(grouped).map(([category, catAnswers]) => (
                    <div key={category} className="mt-6">
                        <h3 className="text-xs uppercase tracking-wider text-[var(--color-accent)]">
                            {category}
                        </h3>
                        <div className="mt-3 space-y-4">
                            {catAnswers.map((answer) => (
                                <div key={answer.id} className="border-b border-[var(--color-border)] pb-4">
                                    <p className="text-sm font-medium text-[var(--color-text)]">
                                        {answer.question_text}
                                    </p>
                                    <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                                        {answer.response_text ?? '(no response)'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {/* Vocational Profile */}
            {profile && (
                <div className="mt-10">
                    <h2 className="text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Vocational Profile
                    </h2>
                    <div className="mt-4 space-y-6 text-sm">
                        {profile.opening_synthesis && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Opening Synthesis</h3>
                                <p className="mt-1 text-[var(--color-text-secondary)]">{profile.opening_synthesis}</p>
                            </div>
                        )}
                        {profile.vocational_orientation && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Vocational Orientation</h3>
                                <p className="mt-1 text-[var(--color-text-secondary)]">{profile.vocational_orientation}</p>
                            </div>
                        )}
                        {profile.primary_domain && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Primary Domain</h3>
                                <p className="mt-1 text-[var(--color-text-secondary)]">{profile.primary_domain}</p>
                            </div>
                        )}
                        {profile.mode_of_work && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Mode of Work</h3>
                                <p className="mt-1 text-[var(--color-text-secondary)]">{profile.mode_of_work}</p>
                            </div>
                        )}
                        {profile.primary_pathways && profile.primary_pathways.length > 0 && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Primary Pathways</h3>
                                <ul className="mt-1 list-inside list-disc text-[var(--color-text-secondary)]">
                                    {profile.primary_pathways.map((p, i) => (
                                        <li key={i}>{p}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {profile.next_steps && profile.next_steps.length > 0 && (
                            <div>
                                <h3 className="font-medium text-[var(--color-text)]">Next Steps</h3>
                                <ul className="mt-1 list-inside list-disc text-[var(--color-text-secondary)]">
                                    {profile.next_steps.map((s, i) => (
                                        <li key={i}>{s}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
