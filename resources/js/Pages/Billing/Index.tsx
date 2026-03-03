import AppLayout from '../../Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';

interface Subscription {
    stripe_status: string;
    stripe_price: string;
    current_period_end: string;
}

interface Props {
    subscription: Subscription | null;
    credits: number;
    usage: {
        assessments_this_period: number;
        assessments_limit: number | null;
    };
    plan_name: string | null;
}

export default function BillingIndex({ subscription, credits, usage, plan_name }: Props) {
    const isActive = subscription?.stripe_status === 'active';

    return (
        <AppLayout title="Billing">
            <div>
                <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                    Billing
                </h1>

                {/* Current Plan */}
                <div className="mt-8 border border-[var(--color-divider)] p-6">
                    <h2 className="font-sans text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Current plan
                    </h2>
                    <p className="mt-2 font-serif text-xl text-[var(--color-text)]">
                        {plan_name ?? 'Free'}
                    </p>
                    {isActive && subscription?.current_period_end && (
                        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                            Renews {new Date(subscription.current_period_end).toLocaleDateString('en-US', {
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </p>
                    )}
                </div>

                {/* Usage */}
                <div className="mt-6 border border-[var(--color-divider)] p-6">
                    <h2 className="font-sans text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                        Usage this period
                    </h2>
                    <div className="mt-4 space-y-3">
                        <div className="flex justify-between">
                            <span className="text-[var(--color-text)]">Assessments used</span>
                            <span className="font-sans text-[var(--color-text)]">
                                {usage.assessments_this_period}
                                {usage.assessments_limit !== null && ` / ${usage.assessments_limit}`}
                            </span>
                        </div>
                        {credits > 0 && (
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text)]">Credits remaining</span>
                                <span className="font-sans text-[var(--color-text)]">{credits}</span>
                            </div>
                        )}
                    </div>

                    {usage.assessments_limit !== null && (
                        <div className="mt-4">
                            <div className="h-1.5 w-full bg-[var(--color-divider)]">
                                <div
                                    className="h-1.5 bg-[var(--color-text)]"
                                    style={{
                                        width: `${Math.min(
                                            (usage.assessments_this_period / usage.assessments_limit) * 100,
                                            100
                                        )}%`,
                                    }}
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div className="mt-8 space-y-3">
                    {isActive ? (
                        <button
                            onClick={() => router.visit('/billing/portal')}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                        >
                            Manage subscription
                        </button>
                    ) : (
                        <button
                            onClick={() => router.visit('/pricing')}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                        >
                            View plans
                        </button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
