import { FormEvent } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function ForgotPassword() {
    const { status } = usePage<{ status?: string }>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AppLayout title="Forgot Password">
            <div className="flex min-h-[70vh] flex-col">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Reset your password
                    </h1>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        Enter your email address and we&apos;ll send you a link to reset your
                        password.
                    </p>

                    {status && (
                        <p className="mt-6 font-sans text-sm text-green-700">
                            {status}
                        </p>
                    )}

                    <form onSubmit={submit} className="mt-10 space-y-6">
                        <div>
                            <label
                                htmlFor="email"
                                className="block font-sans text-sm tracking-wide text-[var(--color-text-secondary)]"
                            >
                                Email
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text)] outline-none transition-colors focus:border-[var(--color-text)]"
                                required
                                autoFocus
                            />
                            {errors.email && (
                                <p className="mt-1 font-sans text-sm text-red-700">{errors.email}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            Send reset link
                        </button>
                    </form>
                </div>

                <div className="mt-16">
                    <Link
                        href="/login"
                        className="font-sans text-sm text-[var(--color-text-secondary)] underline underline-offset-4 transition-colors hover:text-[var(--color-text)]"
                    >
                        &larr; Back to sign in
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
