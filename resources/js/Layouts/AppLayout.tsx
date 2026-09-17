import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';
import PlaceNav from '../Components/PlaceNav';

interface Props {
    title?: string;
    /**
     * Which room this page is in. DESIGN.md reserves the dark surface for the
     * coach and the vocational brain — the places where the student is talking
     * rather than reading. Declared by the page, painted here, so a dark page
     * never has to bleed past the layout's own canvas to get there.
     */
    surface?: 'light' | 'dark';
    children: ReactNode;
}

export default function AppLayout({ title, surface = 'light', children }: Props) {
    return (
        <>
            <Head title={title ? `${title} — Vocation Finder` : 'Vocation Finder'} />
            <div className={`min-h-screen ${surface === 'dark' ? 'surface-dark' : 'bg-[var(--color-background)]'}`}>
                <main className="mx-auto max-w-[640px] px-6 py-16">
                    {/*
                      * The places are rendered here and nowhere else, so a
                      * page cannot forget to be reachable from the rest of
                      * the product. It renders nothing for a signed-out
                      * visitor, which is why the assessment and the results
                      * page can keep using this layout unchanged.
                      */}
                    <PlaceNav />
                    {children}
                </main>
            </div>
        </>
    );
}
