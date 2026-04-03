import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props {
    title?: string;
    children: ReactNode;
}

const navItems = [
    { label: 'Dashboard', href: '/admin' },
    { label: 'Users', href: '/admin/users' },
    { label: 'Organizations', href: '/admin/organizations' },
    { label: 'Assessments', href: '/admin/assessments' },
    { label: 'Questions', href: '/admin/questions' },
    { label: 'Question Categories', href: '/admin/question-categories' },
    { label: 'Vocational Categories', href: '/admin/vocational-categories' },
    { label: 'Courses', href: '/admin/courses' },
    { label: 'Feature Flags', href: '/admin/feature-flags' },
];

export default function AdminLayout({ title, children }: Props) {
    return (
        <>
            <Head title={title ? `${title} — Admin` : 'Admin — Vocation Finder'} />
            <div className="min-h-screen bg-[var(--color-background)]">
                <div className="flex">
                    {/* Sidebar */}
                    <aside className="sticky top-0 h-screen w-56 shrink-0 border-r border-[var(--color-divider)] bg-[var(--color-surface)] px-4 py-8">
                        <Link
                            href="/dashboard"
                            className="mb-8 block font-serif text-lg text-[var(--color-text)]"
                        >
                            Vocation Finder
                        </Link>
                        <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            Admin
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
