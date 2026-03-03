import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props {
    title?: string;
    orgName: string;
    orgSlug: string;
    children: ReactNode;
}

export default function OrgLayout({ title, orgName, orgSlug, children }: Props) {
    const navItems = [
        { label: 'Dashboard', href: `/org/${orgSlug}` },
        { label: 'Members', href: `/org/${orgSlug}/members` },
        { label: 'Insights', href: `/org/${orgSlug}/insights` },
    ];

    return (
        <>
            <Head title={title ? `${title} — ${orgName}` : `${orgName} — Vocation Finder`} />
            <div className="min-h-screen bg-[var(--color-background)]">
                <div className="flex">
                    {/* Sidebar */}
                    <aside className="sticky top-0 h-screen w-56 shrink-0 border-r border-[var(--color-divider)] bg-[var(--color-surface)] px-4 py-8">
                        <Link
                            href="/dashboard"
                            className="mb-2 block font-serif text-lg text-[var(--color-text)]"
                        >
                            Vocation Finder
                        </Link>
                        <p className="mb-8 font-sans text-xs text-[var(--color-accent)]">
                            {orgName}
                        </p>
                        <nav className="space-y-1">
                            {navItems.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="block px-3 py-2 font-sans text-sm text-[var(--color-text)] transition-colors hover:bg-[var(--color-stone-200)]"
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </aside>

                    {/* Main content */}
                    <main className="mx-auto w-full max-w-6xl px-8 py-8">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
