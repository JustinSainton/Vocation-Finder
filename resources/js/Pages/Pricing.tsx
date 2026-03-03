import AppLayout from '../Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';

interface Plan {
    price_id: string;
    credits_per_period?: number;
    member_limit?: number;
    assessments_per_period?: number;
}

interface Props {
    plans: Record<string, Plan>;
}

export default function Pricing({ plans }: Props) {
    const { auth } = usePage<{ auth: { user: { id: string } | null } }>().props;

    const handleCheckout = (plan: string) => {
        if (!auth.user) {
            router.visit('/register');
            return;
        }
        router.post(
            plan.startsWith('org_')
                ? '/billing/checkout/organization'
                : '/billing/checkout/individual',
            { plan }
        );
    };

    return (
        <AppLayout title="Pricing">
            <div>
                <h1 className="font-serif text-3xl tracking-tight text-[var(--color-text)]">
                    Choose your path
                </h1>
                <p className="mt-4 text-[var(--color-text-secondary)]">
                    Your first assessment is free. Unlock deeper insights with a plan.
                </p>

                <div className="mt-12 space-y-8">
                    {/* Individual Plans */}
                    <div>
                        <h2 className="font-sans text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                            Individual
                        </h2>
                        <div className="mt-4 space-y-4">
                            <div className="border border-[var(--color-border)] p-6">
                                <div className="flex items-baseline justify-between">
                                    <h3 className="font-serif text-xl text-[var(--color-text)]">Monthly</h3>
                                    <span className="text-sm text-[var(--color-text-secondary)]">
                                        {plans.individual_monthly?.credits_per_period} assessments/month
                                    </span>
                                </div>
                                <button
                                    onClick={() => handleCheckout('individual_monthly')}
                                    className="mt-4 w-full bg-[var(--color-text)] py-3 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                >
                                    Subscribe monthly
                                </button>
                            </div>

                            <div className="border border-[var(--color-border)] p-6">
                                <div className="flex items-baseline justify-between">
                                    <h3 className="font-serif text-xl text-[var(--color-text)]">Yearly</h3>
                                    <span className="text-sm text-[var(--color-text-secondary)]">
                                        {plans.individual_yearly?.credits_per_period} assessments/year
                                    </span>
                                </div>
                                <button
                                    onClick={() => handleCheckout('individual_yearly')}
                                    className="mt-4 w-full bg-[var(--color-text)] py-3 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                >
                                    Subscribe yearly
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Organization Plans */}
                    <div>
                        <h2 className="font-sans text-sm font-medium uppercase tracking-wider text-[var(--color-text-secondary)]">
                            Organization
                        </h2>
                        <div className="mt-4 space-y-4">
                            <div className="border border-[var(--color-border)] p-6">
                                <div className="flex items-baseline justify-between">
                                    <h3 className="font-serif text-xl text-[var(--color-text)]">Monthly</h3>
                                    <span className="text-sm text-[var(--color-text-secondary)]">
                                        Up to {plans.org_monthly?.member_limit} members,{' '}
                                        {plans.org_monthly?.assessments_per_period} assessments/month
                                    </span>
                                </div>
                                <button
                                    onClick={() => handleCheckout('org_monthly')}
                                    className="mt-4 w-full bg-[var(--color-text)] py-3 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                >
                                    Subscribe monthly
                                </button>
                            </div>

                            <div className="border border-[var(--color-border)] p-6">
                                <div className="flex items-baseline justify-between">
                                    <h3 className="font-serif text-xl text-[var(--color-text)]">Yearly</h3>
                                    <span className="text-sm text-[var(--color-text-secondary)]">
                                        Up to {plans.org_yearly?.member_limit} members,{' '}
                                        {plans.org_yearly?.assessments_per_period} assessments/year
                                    </span>
                                </div>
                                <button
                                    onClick={() => handleCheckout('org_yearly')}
                                    className="mt-4 w-full bg-[var(--color-text)] py-3 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                >
                                    Subscribe yearly
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
