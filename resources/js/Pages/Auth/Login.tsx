import { FormEvent } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Login() {
    const { flash } = usePage<{ flash: { error?: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <AppLayout title="Sign In">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Welcome back
                    </h1>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        Sign in to continue your vocational discernment.
                    </p>

                    {flash?.error && (
                        <p className="mt-6 font-sans text-sm text-red-700">
                            {flash.error}
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

                        <div>
                            <label
                                htmlFor="password"
                                className="block font-sans text-sm tracking-wide text-[var(--color-text-secondary)]"
                            >
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text)] outline-none transition-colors focus:border-[var(--color-text)]"
                                required
                            />
                            {errors.password && (
                                <p className="mt-1 font-sans text-sm text-red-700">{errors.password}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-end">
                            <Link
                                href="/forgot-password"
                                className="font-sans text-sm text-[var(--color-text-secondary)] underline underline-offset-4 transition-colors hover:text-[var(--color-text)]"
                            >
                                Forgot your password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            Sign in
                        </button>

                        <a
                            href="/auth/google/redirect"
                            className="block w-full border border-[var(--color-divider)] bg-transparent py-4 text-center font-sans text-sm tracking-wide text-[var(--color-text)] transition-colors hover:border-[var(--color-text)]"
                        >
                            Continue with Google
                        </a>
                    </form>
                </div>

                <div className="mt-16 text-center">
                    <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                        Don&apos;t have an account?{' '}
                        <Link
                            href="/register"
                            className="text-[var(--color-text)] underline underline-offset-4"
                        >
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
