import { router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

interface Measure {
    key: string;
    label: string;
    count: number;
    of: number;
    rate: number | null;
    why_no_rate: string | null;
    read_these?: boolean;
    unmeasured?: number;
    counts?: Record<string, number>;
}

interface Props {
    days: number;
    readout: {
        sample: { assessments: number; completed: number; minimum_for_a_rate: number };
        measures: Measure[];
    };
}

const WINDOWS = [30, 90, 365, 0];

/**
 * The validation read-out.
 *
 * A rate appears only where the sample can carry one; everywhere else the
 * denominator is printed and the reason is shown in its place. This is the one
 * page in the product where a percentage is allowed at all, and it is allowed
 * because it is about us rather than about a student.
 */
export default function AdminValidation({ days, readout }: Props) {
    const percent = (rate: number) => `${Math.round(rate * 100)}%`;

    return (
        <AdminLayout title="Validation">
            <div className="mb-6 flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-stone-900">Does it work?</h1>
                    <p className="mt-1 text-sm text-stone-500">
                        {readout.sample.assessments} assessments started, {readout.sample.completed} finished. A rate is
                        shown only where there are at least {readout.sample.minimum_for_a_rate} responses behind it.
                    </p>
                </div>
                <div className="flex gap-2">
                    {WINDOWS.map((window) => (
                        <button
                            key={window}
                            type="button"
                            onClick={() => router.get('/admin/validation', { days: window }, { preserveState: true })}
                            className={[
                                'border px-3 py-1 text-sm',
                                window === days ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300',
                            ].join(' ')}
                        >
                            {window === 0 ? 'All time' : `${window}d`}
                        </button>
                    ))}
                </div>
            </div>

            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b border-stone-300 text-left text-stone-500">
                        <th className="py-2 font-normal">Measure</th>
                        <th className="py-2 font-normal">Count</th>
                        <th className="py-2 font-normal">Of</th>
                        <th className="py-2 font-normal">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    {readout.measures.map((measure) => (
                        <tr key={measure.key} className="border-b border-stone-200 align-top">
                            <td className="py-3 pr-4 text-stone-900">
                                {measure.label}
                                {measure.read_these && (
                                    <span className="ml-2 text-xs text-stone-500">— read the comments</span>
                                )}
                                {measure.unmeasured !== undefined && measure.unmeasured > 0 && (
                                    <span className="ml-2 text-xs text-stone-500">
                                        ({measure.unmeasured} never answered both ends)
                                    </span>
                                )}
                            </td>
                            <td className="py-3 pr-4 tabular-nums">{measure.count}</td>
                            <td className="py-3 pr-4 tabular-nums">{measure.of}</td>
                            <td className="py-3 text-stone-900">
                                {measure.rate !== null ? (
                                    <span className="tabular-nums">{percent(measure.rate)}</span>
                                ) : (
                                    <span className="text-stone-400">{measure.why_no_rate}</span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <p className="mt-6 max-w-2xl text-xs text-stone-500">
                Segmenting these numbers is limited to what students told us about themselves — income band, grade
                level and school. We do not infer culture, gender or neurodiversity from names or writing in order to
                test for bias against them, because doing so would create the record we declined to collect.
            </p>
        </AdminLayout>
    );
}
