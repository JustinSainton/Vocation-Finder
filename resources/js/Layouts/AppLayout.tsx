import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props {
    title?: string;
    children: ReactNode;
}

export default function AppLayout({ title, children }: Props) {
    return (
        <>
            <Head title={title ? `${title} — Vocation Finder` : 'Vocation Finder'} />
            <div className="min-h-screen bg-[var(--color-background)]">
                <main className="mx-auto max-w-[640px] px-6 py-16">
                    {children}
                </main>
            </div>
        </>
    );
}
