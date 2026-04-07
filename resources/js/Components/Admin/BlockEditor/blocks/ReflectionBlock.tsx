import type { ReflectionBlock } from '../types';

interface Props {
    block: ReflectionBlock;
    onChange: (block: ReflectionBlock) => void;
}

export function ReflectionBlockEditor({ block, onChange }: Props) {
    return (
        <div>
            <p className="mb-2 font-sans text-xs text-[var(--color-text-secondary)]">
                Write a reflection prompt for learners to consider. This will be displayed
                with a contemplative styling to encourage deeper thinking.
            </p>
            <textarea
                value={block.prompt}
                onChange={(e) => onChange({ ...block, prompt: e.target.value })}
                placeholder="e.g. Consider how this connects to your own experience..."
                rows={3}
                className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-serif text-[var(--color-text)] italic outline-none focus:border-[var(--color-text)]"
            />
        </div>
    );
}
