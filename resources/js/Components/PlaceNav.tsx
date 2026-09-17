import { Link, usePage } from '@inertiajs/react';

interface Place {
    key: string;
    label: string;
    blurb: string;
    href: string;
}

/**
 * The five places, rendered from the list the server sends.
 *
 * There are deliberately no hrefs written in this file. A place that is not
 * built has no route, so the server never sends it, so it cannot appear here
 * as a link to nowhere — and a place that gets built appears without this
 * component being edited.
 *
 * Which surface this is drawn on is decided in CSS (`.place-nav`), not by a
 * prop, so the layout stays the only thing that knows which room it is in.
 */
export default function PlaceNav() {
    const page = usePage<{ places?: Place[] }>();
    const places = (page.props.places ?? []) as Place[];

    if (places.length === 0) {
        return null;
    }

    const path = page.url.split('?')[0];

    return (
        <nav aria-label="Places" className="place-nav">
            {places.map((place) => {
                const here = path === place.href;

                return (
                    <Link
                        key={place.key}
                        href={place.href}
                        title={place.blurb}
                        aria-current={here ? 'page' : undefined}
                        className={`type-eyebrow${here ? ' place-here' : ''}`}
                    >
                        {place.label}
                    </Link>
                );
            })}
        </nav>
    );
}
