import { useEffect, useRef, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface VocationalProfile {
    id: string;
    opening_synthesis: string;
    vocational_orientation: string;
    primary_pathways: string[];
    specific_considerations: string;
    next_steps: string[];
    ministry_integration: string;
    primary_domain: string;
    mode_of_work: string;
    secondary_orientation: string;
}

interface Props {
    assessment_id: string;
    guest_token: string | null;
    status: string;
    profile: VocationalProfile | null;
    tier?: string;
    upgrade_message?: string;
}

export default function Results({
    assessment_id,
    guest_token,
    status: initialStatus,
    profile: initialProfile,
    tier,
    upgrade_message,
}: Props) {
    const [profile, setProfile] = useState<VocationalProfile | null>(initialProfile);
    const [polling, setPolling] = useState(initialStatus === 'analyzing');
    const [email, setEmail] = useState('');
    const [emailSent, setEmailSent] = useState(false);
    const [emailSending, setEmailSending] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!polling || profile) return;

        const fetchResults = async () => {
            try {
                const headers: Record<string, string> = {
                    'Content-Type': 'application/json',
                };
                if (guest_token) {
                    headers['X-Guest-Token'] = guest_token;
                }

                const res = await fetch(
                    `/api/v1/assessments/${assessment_id}/results`,
                    { headers }
                );

                if (res.status === 200) {
                    const data = await res.json();
                    setProfile(data.data);
                    setPolling(false);
                }
            } catch {
                // Keep polling
            }
        };

        fetchResults();
        pollRef.current = setInterval(fetchResults, 5000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [polling, profile]);

    const handleEmail = async () => {
        if (!email.trim()) return;
        setEmailSending(true);
        try {
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
            };
            if (guest_token) headers['X-Guest-Token'] = guest_token;

            await fetch(`/api/v1/assessments/${assessment_id}/results/email`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ email: email.trim() }),
            });
            setEmailSent(true);
        } catch {
            // silent
        } finally {
            setEmailSending(false);
        }
    };

    // Still analyzing
    if (!profile) {
        return (
            <AppLayout title="Preparing Your Portrait">
                <div className="flex min-h-[60vh] flex-col items-center justify-center">
                    <h1 className="mb-4 font-serif text-2xl text-[var(--color-text)]">
                        Preparing your portrait
                    </h1>
                    <p className="max-w-sm text-center text-lg leading-relaxed text-[var(--color-text-secondary)]">
                        We are looking across everything you shared — not isolated answers,
                        but the story they tell together. This typically takes 1–2 minutes.
                    </p>
                    <div className="mt-8 h-1 w-32 animate-pulse bg-[var(--color-divider)]" />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Your Vocational Portrait">
            <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Your vocational articulation
            </p>

            <h1 className="mb-8 font-serif text-3xl tracking-tight text-[var(--color-text)]">
                Vocational Portrait
            </h1>

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Opening Synthesis */}
            <p className="text-xl leading-relaxed text-[var(--color-text)]">
                {profile.opening_synthesis}
            </p>

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Vocational Orientation */}
            <SectionHeader>Vocational Orientation</SectionHeader>
            <p className="mb-6 text-lg leading-relaxed text-[var(--color-text)]">
                {profile.vocational_orientation}
            </p>

            {/* Meta badges */}
            <div className="mt-6 grid grid-cols-2 gap-6">
                <MetaBadge label="Primary Domain" value={profile.primary_domain} />
                <MetaBadge label="Mode of Work" value={profile.mode_of_work} />
            </div>
            {profile.secondary_orientation && (
                <div className="mt-4">
                    <MetaBadge
                        label="Secondary Orientation"
                        value={profile.secondary_orientation}
                    />
                </div>
            )}

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Primary Pathways */}
            {profile.primary_pathways?.length > 0 && (
                <>
                    <SectionHeader>Primary Pathways</SectionHeader>
                    <div className="space-y-3">
                        {profile.primary_pathways.map((pathway, i) => (
                            <div
                                key={i}
                                className="border-l-[3px] border-[var(--color-accent)] bg-[var(--color-surface)] px-5 py-4"
                            >
                                <p className="text-[15px] leading-relaxed text-[var(--color-stone-800)]">
                                    {pathway}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="my-8 h-px bg-[var(--color-divider)]" />
                </>
            )}

            {/* Specific Considerations */}
            {profile.specific_considerations && (
                <>
                    <SectionHeader>Specific Considerations</SectionHeader>
                    <p className="mb-6 text-lg leading-relaxed text-[var(--color-text)]">
                        {profile.specific_considerations}
                    </p>
                    <div className="my-8 h-px bg-[var(--color-divider)]" />
                </>
            )}

            {/* Next Steps */}
            {profile.next_steps?.length > 0 && (
                <>
                    <SectionHeader>Next Steps</SectionHeader>
                    <div className="space-y-4">
                        {profile.next_steps.map((step, i) => (
                            <div key={i} className="flex items-start gap-4">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--color-text)] font-sans text-xs text-[var(--color-background)]">
                                    {i + 1}
                                </span>
                                <p className="flex-1 text-[15px] leading-relaxed text-[var(--color-stone-800)]">
                                    {step}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="my-8 h-px bg-[var(--color-divider)]" />
                </>
            )}

            {/* Ministry Integration */}
            {profile.ministry_integration && (
                <>
                    <SectionHeader>Ministry Integration</SectionHeader>
                    <p className="mb-6 text-lg leading-relaxed text-[var(--color-text)]">
                        {profile.ministry_integration}
                    </p>
                    <div className="my-8 h-px bg-[var(--color-divider)]" />
                </>
            )}

            {/* Upgrade prompt */}
            {tier === 'free' && upgrade_message && (
                <div className="my-8 border border-[var(--color-divider)] p-6">
                    <p className="italic text-[var(--color-text)]">{upgrade_message}</p>
                </div>
            )}

            {/* Email results */}
            <h2 className="mb-2 font-serif text-xl text-[var(--color-text)]">
                Save your results
            </h2>
            <p className="mb-4 text-[var(--color-text-secondary)]">
                Enter your email to receive a beautifully formatted copy of your
                vocational portrait.
            </p>

            {emailSent ? (
                <p className="text-[var(--color-accent)]">Sent — check your inbox.</p>
            ) : (
                <div className="flex gap-3">
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="your@email.com"
                        className="flex-1 border-0 border-b border-[var(--color-divider)] bg-transparent px-0 py-2 font-serif text-lg text-[var(--color-text)] outline-none placeholder:text-[var(--color-stone-400)] focus:border-[var(--color-text)]"
                    />
                    <button
                        onClick={handleEmail}
                        disabled={emailSending || !email.trim()}
                        className="bg-[var(--color-text)] px-6 py-2 font-sans text-sm text-[var(--color-background)] disabled:opacity-30"
                    >
                        {emailSending ? 'Sending...' : 'Send'}
                    </button>
                </div>
            )}

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Disclaimer */}
            <p className="text-center text-sm italic text-[var(--color-accent)]">
                This vocational portrait was generated with the assistance of artificial
                intelligence based on your written reflections. It is intended as a tool
                for discernment, not a definitive assessment.
            </p>

            {/* Actions */}
            <div className="mt-8 space-y-3">
                <a
                    href="/"
                    className="block w-full bg-[var(--color-text)] py-4 text-center font-sans text-sm tracking-wide text-[var(--color-background)]"
                >
                    Return home
                </a>
                <a
                    href="/assessment"
                    className="block w-full border border-[var(--color-divider)] py-4 text-center font-sans text-sm tracking-wide text-[var(--color-text)]"
                >
                    Take assessment again
                </a>
            </div>
        </AppLayout>
    );
}

function SectionHeader({ children }: { children: React.ReactNode }) {
    return (
        <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
            {children}
        </p>
    );
}

function MetaBadge({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="font-sans text-xs text-[var(--color-accent)]">{label}</p>
            <p className="mt-1 text-lg text-[var(--color-text)]">{value}</p>
        </div>
    );
}
