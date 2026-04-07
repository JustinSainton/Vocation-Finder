import { useState, useRef, useEffect } from 'react';
import { BLOCK_TYPE_LABELS, BLOCK_TYPE_DESCRIPTIONS, type BlockType } from './types';

interface Props {
    onAdd: (type: BlockType) => void;
}

const blockTypes: BlockType[] = ['text', 'reflection', 'checkpoint', 'video', 'image', 'pdf'];

export function BlockToolbar({ onAdd }: Props) {
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const handleClick = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, [open]);

    return (
        <div className="relative" ref={menuRef}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="font-sans text-sm text-[var(--color-accent)] transition-colors hover:text-[var(--color-text)]"
            >
                + Add block
            </button>

            {open && (
                <div className="absolute left-0 top-full z-10 mt-1 w-64 border border-[var(--color-divider)] bg-[var(--color-background)] shadow-sm">
                    {blockTypes.map((type) => (
                        <button
                            key={type}
                            type="button"
                            onClick={() => {
                                onAdd(type);
                                setOpen(false);
                            }}
                            className="flex w-full flex-col px-4 py-3 text-left transition-colors hover:bg-[var(--color-stone-100)]"
                        >
                            <span className="font-sans text-sm font-medium text-[var(--color-text)]">
                                {BLOCK_TYPE_LABELS[type]}
                            </span>
                            <span className="font-sans text-xs text-[var(--color-text-secondary)]">
                                {BLOCK_TYPE_DESCRIPTIONS[type]}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
