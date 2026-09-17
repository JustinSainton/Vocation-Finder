import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface Cost {
    published: number;
    published_label: string;
    estimated: number | null;
    basis: string;
    caveat: string;
}

interface Result {
    id: string;
    slug: string;
    name: string;
    where: string;
    kind: string;
    control: string;
    standing: { value: string; label: string; description: string };
    cost: Cost;
    programs: string[];
}

interface Props {
    filters: Record<string, string | undefined>;
    results: Result[];
    list: { id: string; slug: string; name: string; standing: string }[];
    balance: string[];
    knows_gpa: boolean;
    knows_income_band: boolean;
}

const money = (value: number) => `$${value.toLocaleString()}`;

/**
 * The explorer.
 *
 * Every school is rendered the same way: nothing is highlighted, nothing is
 * ranked, and no standing carries a colour. DESIGN.md forbids colour-coding
 * categories and confidence, and a green "likely" beside a red "reach" would
 * be that rule broken in the place it matters most — a student reading colour
 * before words learns to avoid the red ones, which is precisely the opposite
 * of how a college list should be built.
 *
 * The cost shown first is what students in their bracket actually paid. The
 * published price is underneath, in smaller type, where it belongs.
 */
export default function CollegesIndex({ filters, results, list, balance, knows_gpa, knows_income_band }: Props) {
    const [state, setState] = useState(filters.state ?? '');
    const [kind, setKind] = useState(filters.kind ?? '');
    const [standing, setStanding] = useState(filters.standing ?? '');
    const [gpa, setGpa] = useState('');
    const [band, setBand] = useState('');

    const apply = (next: Record<string, string>) => {
        router.get('/plan/colleges', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout title="Colleges">
            <p className="type-eyebrow">Plan · Colleges</p>
            <h1 className="mt-2 font-serif text-3xl leading-tight text-[var(--color-text)]">
                Schools that teach what you have been talking about
            </h1>
            <p className="mt-3 text-[var(--color-text-secondary)]">
                These are here because of a programme they actually run, not because anyone decided they suit you.
                Filter them, argue with them, take them to somebody who knows you.
            </p>

            {(!knows_gpa || !knows_income_band) && (
                <section className="mt-8 border border-[var(--color-divider)] p-5">
                    <p className="type-eyebrow">Two things we cannot work out for you</p>
                    <p className="mt-2 text-[var(--color-text-secondary)]">
                        {!knows_gpa && 'Your GPA tells us where you stand against what each school published. '}
                        {!knows_income_band && 'Your household income bracket is what turns a sticker price into the number families like yours actually paid. '}
                        Neither is required, and neither is shared with anyone.
                    </p>
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                        {!knows_gpa && (
                            <label className="flex flex-col">
                                <span className="type-eyebrow">GPA</span>
                                <input
                                    value={gpa}
                                    onChange={(event) => setGpa(event.target.value)}
                                    inputMode="decimal"
                                    placeholder="3.4"
                                    className="mt-1 w-24 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                                />
                            </label>
                        )}
                        {!knows_income_band && (
                            <label className="flex flex-col">
                                <span className="type-eyebrow">Household income</span>
                                <select
                                    value={band}
                                    onChange={(event) => setBand(event.target.value)}
                                    className="mt-1 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                                >
                                    <option value="">Rather not say</option>
                                    <option value="up_to_30k">Up to $30,000</option>
                                    <option value="from_30k_to_48k">$30,000 to $48,000</option>
                                    <option value="from_48k_to_75k">$48,000 to $75,000</option>
                                    <option value="from_75k_to_110k">$75,000 to $110,000</option>
                                    <option value="over_110k">Over $110,000</option>
                                </select>
                            </label>
                        )}
                        <button
                            type="button"
                            onClick={() =>
                                router.patch(
                                    '/plan/colleges/inputs',
                                    { gpa: gpa || null, household_income_band: band || null },
                                    { preserveScroll: true },
                                )
                            }
                            className="border border-[var(--color-divider)] px-4 py-2 text-[var(--color-text)]"
                        >
                            Save
                        </button>
                    </div>
                </section>
            )}

            {list.length > 0 && (
                <section className="mt-10 border-t border-[var(--color-divider)] pt-6">
                    <p className="type-eyebrow">Your list</p>
                    <ul className="mt-3 space-y-1">
                        {list.map((item) => (
                            <li key={item.id}>
                                <Link href={`/plan/colleges/${item.slug}`} className="underline">
                                    {item.name}
                                </Link>
                                <span className="ml-2 text-[var(--color-text-muted)]">{item.standing}</span>
                            </li>
                        ))}
                    </ul>
                    {balance.map((line) => (
                        <p key={line} className="mt-3 text-[var(--color-text-secondary)]">
                            {line}
                        </p>
                    ))}
                </section>
            )}

            <section className="mt-10 flex flex-wrap gap-3 border-t border-b border-[var(--color-divider)] py-4">
                <input
                    aria-label="State"
                    value={state}
                    onChange={(event) => setState(event.target.value.toUpperCase().slice(0, 2))}
                    onBlur={() => apply({ state })}
                    placeholder="State"
                    className="w-24 border border-[var(--color-divider)] bg-transparent px-2 py-1"
                />
                <select
                    aria-label="Kind"
                    value={kind}
                    onChange={(event) => {
                        setKind(event.target.value);
                        apply({ kind: event.target.value });
                    }}
                    className="border border-[var(--color-divider)] bg-transparent px-2 py-1"
                >
                    <option value="">Any kind of school</option>
                    <option value="university">University</option>
                    <option value="liberal_arts">Liberal arts</option>
                    <option value="community">Community college</option>
                    <option value="technical">Technical or trade</option>
                </select>
                <select
                    aria-label="Standing"
                    value={standing}
                    onChange={(event) => {
                        setStanding(event.target.value);
                        apply({ standing: event.target.value });
                    }}
                    className="border border-[var(--color-divider)] bg-transparent px-2 py-1"
                >
                    <option value="">Any standing</option>
                    <option value="reach">A reach</option>
                    <option value="possible">In range</option>
                    <option value="likely">Likely</option>
                </select>
            </section>

            <ul className="mt-8 space-y-8">
                {results.map((result) => (
                    <li key={result.id} className="border-b border-[var(--color-divider)] pb-8">
                        <Link href={`/plan/colleges/${result.slug}`} className="font-serif text-xl text-[var(--color-text)] underline">
                            {result.name}
                        </Link>
                        <p className="mt-1 text-[var(--color-text-muted)]">
                            {result.where} · {result.kind} · {result.control}
                        </p>

                        {result.programs.length > 0 && (
                            <p className="mt-3 text-[var(--color-text-secondary)]">
                                Here because they run {result.programs.join(', ')}.
                            </p>
                        )}

                        <p className="mt-3 text-[var(--color-text)]">{result.standing.label}</p>
                        <p className="text-[var(--color-text-secondary)]">{result.standing.description}</p>

                        <p className="mt-3 font-serif text-lg text-[var(--color-text)]">
                            {result.cost.estimated !== null ? money(result.cost.estimated) : money(result.cost.published)} a year
                        </p>
                        <p className="text-[var(--color-text-muted)]">{result.cost.basis}</p>
                        {result.cost.estimated !== null && (
                            <p className="text-[var(--color-text-muted)]">
                                {result.cost.published_label}: {money(result.cost.published)}
                            </p>
                        )}
                    </li>
                ))}
            </ul>

            {results.length === 0 && (
                <p className="mt-8 text-[var(--color-text-secondary)]">
                    Nothing matches those filters. Widen one — the list is not a judgement about what is available to you.
                </p>
            )}
        </AppLayout>
    );
}
