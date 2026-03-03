import { FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Register() {
    // Grab guest_token from URL if present (for linking assessment to new account)
    const params = new URLSearchParams(window.location.search);
    const guestToken = params.get('guest_token') ?? '';

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        guest_token: guestToken,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <AppLayout title="Create Account">
            <div className="flex min-h-[70vh] flex-col justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Create your account
                    </h1>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        Save your results and continue your discernment over time.
                    </p>

                    <form onSubmit={submit} className="mt-10 space-y-6">
                        <input type="hidden" name="guest_token" value={data.guest_token} />

                        <div>
                            <label
                                htmlFor="name"
                                className="block font-sans text-sm tracking-wide text-[var(--color-text-secondary)]"
                            >
                                Name
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text)] outline-none transition-colors focus:border-[var(--color-text)]"
                                required
                                autoFocus
                            />
                            {errors.name && (
                                <p className="mt-1 font-sans text-sm text-red-700">{errors.name}</p>
                            )}
                        </div>

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

                        <div>
                            <label
                                htmlFor="password_confirmation"
                                className="block font-sans text-sm tracking-wide text-[var(--color-text-secondary)]"
                            >
                                Confirm password
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text)] outline-none transition-colors focus:border-[var(--color-text)]"
                                required
                            />
                            {errors.password_confirmation && (
                                <p className="mt-1 font-sans text-sm text-red-700">
                                    {errors.password_confirmation}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[var(--color-text)] py-4 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            Create account
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
                        Already have an account?{' '}
                        <Link
                            href="/login"
                            className="text-[var(--color-text)] underline underline-offset-4"
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
