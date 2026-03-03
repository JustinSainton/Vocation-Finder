import AppLayout from '../../Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function Success() {
    return (
        <AppLayout title="Payment Successful">
            <div className="flex min-h-[50vh] flex-col items-center justify-center text-center">
                <h1 className="font-serif text-3xl tracking-tight text-[var(--color-text)]">
                    Welcome aboard
                </h1>
                <p className="mt-4 text-lg text-[var(--color-text-secondary)]">
                    Your subscription is active. You now have full access to vocational discernment assessments.
                </p>
                <button
                    onClick={() => router.visit('/dashboard')}
                    className="mt-8 bg-[var(--color-text)] px-8 py-3 text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                >
                    Go to dashboard
                </button>
            </div>
        </AppLayout>
    );
}
