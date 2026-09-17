import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    token: string;
    organization_name: string;
    invited_email: string;
    role: string;
    blocked_reason: string | null;
    signed_in_as: string | null;
    status?: string;
}

/**
 * What a student sees before they are asked to join anything.
 *
 * It says what the tool does and what their school will and will not see,
 * in that order, because the second question is the one a fifteen-year-old
 * actually has and nobody ever asks it out loud.
 */
export default function InvitationShow() {
    const { token, organization_name, invited_email, blocked_reason, signed_in_as, status } =
        usePage().props as unknown as Props;
    const form = useForm({});

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/invitations/${token}`);
    };

    return (
        <div className="mx-auto max-w-[640px] px-4 py-24">
            <p className="type-eyebrow text-[var(--color-muted)]">{organization_name}</p>

            <h1 className="mt-4 font-serif text-3xl leading-snug text-[var(--color-text)]">
                {organization_name} has set up a place for you.
            </h1>

            <div className="mt-10 space-y-5 text-[var(--color-body)]">
                <p>
                    It starts with twenty questions about what you are actually like — not what you are good
                    at on paper. At the end you get one concrete thing to do next, and a coach that has read
                    everything you wrote.
                </p>
                <p>
                    {organization_name} can see whether you have started, what you have finished, and if you
                    have named something they could help with. They cannot see your conversations with the
                    coach, or anything you write about yourself. That part stays yours.
                </p>
            </div>

            {status && (
                <p className="mt-8 border-l-2 border-[var(--color-divider)] pl-4 text-[var(--color-body)]">
                    {status}
                </p>
            )}

            {blocked_reason ? (
                <p className="mt-10 border-l-2 border-[var(--color-divider)] pl-4 text-[var(--color-body)]">
                    {blocked_reason}
                </p>
            ) : (
                <form onSubmit={submit} className="mt-10">
                    <button type="submit" disabled={form.processing} className="action-primary">
                        Start
                    </button>

                    <p className="mt-4 font-sans text-sm text-[var(--color-muted)]">
                        {signed_in_as
                            ? `You are signed in as ${signed_in_as}. This will join that account.`
                            : `Invited at ${invited_email}. You will make an account next — it takes a minute.`}
                    </p>
                </form>
            )}
        </div>
    );
}
