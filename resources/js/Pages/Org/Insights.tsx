import OrgLayout from '../../Layouts/OrgLayout';

interface OrgRef {
    id: string;
    name: string;
    slug: string;
}

interface DistributionItem {
    label: string;
    count: number;
    percentage: number;
}

interface CompletionMonth {
    month: string;
    started: number;
    completed: number;
    rate: number;
}

interface CategoryScore {
    category: string;
    average: number;
    respondents: number;
}

interface Props {
    organization: OrgRef;
    domainDistribution: DistributionItem[];
    modeDistribution: DistributionItem[];
    completionByMonth: CompletionMonth[];
    categoryScores: CategoryScore[];
    totalProfiles: number;
}

export default function OrgInsights({
    organization,
    domainDistribution,
    modeDistribution,
    completionByMonth,
    categoryScores,
    totalProfiles,
}: Props) {
    return (
        <OrgLayout title="Insights" orgName={organization.name} orgSlug={organization.slug}>
            <h1 className="mb-2 font-serif text-2xl text-[var(--color-text)]">Insights</h1>
            <p className="mb-10 text-sm text-[var(--color-text-secondary)]">
                Anonymized aggregate data across {totalProfiles} completed assessments.
            </p>

            {totalProfiles === 0 ? (
                <p className="py-16 text-center text-[var(--color-text-secondary)]">
                    No completed assessments yet. Insights will appear once members complete
                    their assessments.
                </p>
            ) : (
                <div className="space-y-12">
                    {/* Primary Domain Distribution */}
                    <Section title="Primary Domain Distribution">
                        <div className="space-y-3">
                            {domainDistribution.map((item) => (
                                <BarRow
                                    key={item.label}
                                    label={item.label}
                                    value={`${item.count} (${item.percentage}%)`}
                                    percentage={item.percentage}
                                />
                            ))}
                        </div>
                    </Section>

                    {/* Mode of Work Distribution */}
                    {modeDistribution.length > 0 && (
                        <Section title="Mode of Work">
                            <div className="space-y-3">
                                {modeDistribution.map((item) => (
                                    <BarRow
                                        key={item.label}
                                        label={item.label}
                                        value={`${item.count} (${item.percentage}%)`}
                                        percentage={item.percentage}
                                    />
                                ))}
                            </div>
                        </Section>
                    )}

                    {/* Completion Rates */}
                    <Section title="Assessment Completion by Month">
                        <div className="space-y-3">
                            {completionByMonth.map((month) => (
                                <div
                                    key={month.month}
                                    className="flex items-center gap-4"
                                >
                                    <span className="w-20 shrink-0 font-sans text-sm text-[var(--color-text-secondary)]">
                                        {month.month}
                                    </span>
                                    <div className="flex-1">
                                        <div className="h-6 w-full bg-[var(--color-stone-200)]">
                                            <div
                                                className="h-full bg-[var(--color-text)]"
                                                style={{
                                                    width: `${month.rate}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <span className="w-28 shrink-0 text-right font-sans text-sm text-[var(--color-text)]">
                                        {month.completed}/{month.started} ({month.rate}%)
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Section>

                    {/* Category Scores */}
                    {categoryScores.length > 0 && (
                        <Section title="Average Category Scores">
                            <div className="space-y-3">
                                {categoryScores.map((item) => (
                                    <BarRow
                                        key={item.category}
                                        label={item.category}
                                        value={`${item.average} avg (${item.respondents} respondents)`}
                                        percentage={Math.min(item.average * 10, 100)}
                                    />
                                ))}
                            </div>
                        </Section>
                    )}
                </div>
            )}

            <div className="mt-12 border-t border-[var(--color-divider)] pt-6">
                <p className="text-center text-sm italic text-[var(--color-accent)]">
                    All data is anonymized. Individual member identities are not associated
                    with specific results in this view.
                </p>
            </div>
        </OrgLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                {title}
            </p>
            {children}
        </div>
    );
}

function BarRow({
    label,
    value,
    percentage,
}: {
    label: string;
    value: string;
    percentage: number;
}) {
    return (
        <div className="flex items-center gap-4">
            <span className="w-40 shrink-0 font-sans text-sm text-[var(--color-text)]">
                {label}
            </span>
            <div className="flex-1">
                <div className="h-6 w-full bg-[var(--color-stone-200)]">
                    <div
                        className="h-full bg-[var(--color-text)]"
                        style={{ width: `${Math.max(percentage, 2)}%` }}
                    />
                </div>
            </div>
            <span className="w-36 shrink-0 text-right font-sans text-sm text-[var(--color-text-secondary)]">
                {value}
            </span>
        </div>
    );
}
