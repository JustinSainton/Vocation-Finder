import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface Props {
    email: string;
    token: string;
}

export default function ResetPassword({ email, token }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <AppLayout title="Reset Password">
            <div className="flex min-h-[70vh] flex-col">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Set a new password
                    </h1>

                    <p className="mt-4 text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        Choose a new password for your account.
                    </p>

                    <form onSubmit={submit} className="mt-10 space-y-6">
                        <input type="hidden" name="token" value={data.token} />

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
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text-secondary)] outline-none"
                                readOnly
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="password"
                                className="block font-sans text-sm tracking-wide text-[var(--color-text-secondary)]"
                            >
                                New password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-2 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-base text-[var(--color-text)] outline-none transition-colors focus:border-[var(--color-text)]"
                                required
                                autoFocus
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
                                Confirm new password
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
                            Reset password
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
