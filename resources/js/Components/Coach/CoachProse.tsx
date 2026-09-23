import { Fragment, ReactNode } from 'react';

/**
 * The coach's words, set as prose.
 *
 * Models write light markdown whether asked to or not. Rendering it raw puts
 * asterisks in front of a sixteen-year-old; rendering it as HTML trusts model
 * output with the DOM. This does neither: paragraphs, short lists, and
 * emphasis, built as React nodes, and nothing else.
 */
export default function CoachProse({ text }: { text: string }) {
    const blocks = text.replace(/\r\n/g, '\n').trim().split(/\n{2,}/);

    return (
        <>
            {blocks.map((block, index) => {
                const lines = block.split('\n');
                const bulleted = lines.every((line) => /^\s*(?:[-*•]|\d+[.)])\s+/.test(line));

                if (bulleted && lines.length > 1) {
                    const ordered = /^\s*\d/.test(lines[0]);
                    const items = lines.map((line) => line.replace(/^\s*(?:[-*•]|\d+[.)])\s+/, ''));
                    const List = ordered ? 'ol' : 'ul';

                    return (
                        <List
                            key={index}
                            className={`mt-3 space-y-1.5 pl-5 first:mt-0 ${ordered ? 'list-decimal' : 'list-disc'} marker:text-[var(--color-on-dark-muted)]`}
                        >
                            {items.map((item, i) => (
                                <li key={i}>{inline(item)}</li>
                            ))}
                        </List>
                    );
                }

                return (
                    <p key={index} className="mt-3 first:mt-0">
                        {lines.map((line, i) => (
                            <Fragment key={i}>
                                {i > 0 && <br />}
                                {inline(line.replace(/^#{1,6}\s+/, ''))}
                            </Fragment>
                        ))}
                    </p>
                );
            })}
        </>
    );
}

function inline(text: string): ReactNode[] {
    const parts = text.split(/(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|_[^_\s][^_]*_)/g);

    return parts.map((part, index) => {
        if (/^\*\*[^*]+\*\*$/.test(part)) {
            return (
                <strong key={index} className="font-medium text-[var(--color-on-dark)]">
                    {part.slice(2, -2)}
                </strong>
            );
        }
        if (/^(\*[^*]+\*|_[^_]+_)$/.test(part)) {
            return <em key={index}>{part.slice(1, -1)}</em>;
        }
        return <Fragment key={index}>{part}</Fragment>;
    });
}
