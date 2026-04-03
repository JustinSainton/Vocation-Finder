import AdminLayout from '../../../Layouts/AdminLayout';
import { router } from '@inertiajs/react';

interface Flag {
    id: string;
    key: string;
    name: string;
    description: string | null;
    is_enabled: boolean;
}

interface Props {
    flags: Flag[];
}

export default function FeatureFlagsIndex({ flags }: Props) {
    const handleToggle = (flag: Flag) => {
        router.put(`/admin/feature-flags/${flag.id}`, {
            is_enabled: !flag.is_enabled,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Feature Flags">
            <h1 className="font-serif text-2xl text-[var(--color-text)]">Feature Flags</h1>
            <p className="mt-1 font-sans text-sm text-[var(--color-text-secondary)]">
                Toggle features on or off across the entire platform. Changes take effect immediately.
            </p>

            <div className="mt-8 space-y-3">
                {flags.map((flag) => (
                    <div
                        key={flag.id}
                        className="flex items-center justify-between border border-[var(--color-border)] px-5 py-4"
                    >
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-3">
                                <h3 className="font-sans text-sm font-medium text-[var(--color-text)]">
                                    {flag.name}
                                </h3>
                                <span className="font-mono text-xs text-[var(--color-text-secondary)]">
                                    {flag.key}
                                </span>
                            </div>
                            {flag.description && (
                                <p className="mt-1 font-sans text-xs text-[var(--color-text-secondary)]">
                                    {flag.description}
                                </p>
                            )}
                        </div>

                        <button
                            onClick={() => handleToggle(flag)}
                            className={`relative ml-4 inline-flex h-6 w-11 shrink-0 cursor-pointer items-center transition-colors duration-200 ${
                                flag.is_enabled
                                    ? 'bg-[var(--color-text)]'
                                    : 'bg-[var(--color-accent)]'
                            }`}
                            role="switch"
                            aria-checked={flag.is_enabled}
                            aria-label={`Toggle ${flag.name}`}
                        >
                            <span
                                className={`inline-block h-4 w-4 transform bg-[var(--color-background)] transition-transform duration-200 ${
                                    flag.is_enabled ? 'translate-x-6' : 'translate-x-1'
                                }`}
                            />
                        </button>
                    </div>
                ))}
            </div>

            {flags.length === 0 && (
                <p className="mt-8 text-center font-sans text-sm text-[var(--color-text-secondary)]">
                    No feature flags configured. Run the seeder to create them.
                </p>
            )}
        </AdminLayout>
    );
}
