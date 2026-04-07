import type { CheckpointBlock } from '../types';

interface Props {
    block: CheckpointBlock;
    onChange: (block: CheckpointBlock) => void;
}

export function CheckpointBlockEditor({ block, onChange }: Props) {
    const addOption = () => {
        onChange({ ...block, options: [...block.options, ''] });
    };

    const updateOption = (index: number, value: string) => {
        const updated = [...block.options];
        updated[index] = value;
        onChange({ ...block, options: updated });
    };

    const removeOption = (index: number) => {
        if (block.options.length <= 2) return;
        onChange({ ...block, options: block.options.filter((_, i) => i !== index) });
    };

    return (
        <div className="space-y-3">
            <div>
                <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                    Question
                </label>
                <textarea
                    value={block.question}
                    onChange={(e) => onChange({ ...block, question: e.target.value })}
                    placeholder="What is the question for this checkpoint?"
                    rows={2}
                    className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                />
            </div>

            <div>
                <label className="mb-2 block font-sans text-xs text-[var(--color-text-secondary)]">
                    Options
                </label>
                <div className="space-y-2">
                    {block.options.map((option, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <span className="flex h-6 w-6 shrink-0 items-center justify-center font-sans text-xs text-[var(--color-accent)]">
                                {String.fromCharCode(65 + i)}
                            </span>
                            <input
                                type="text"
                                value={option}
                                onChange={(e) => updateOption(i, e.target.value)}
                                placeholder={`Option ${String.fromCharCode(65 + i)}`}
                                className="flex-1 border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                            />
                            {block.options.length > 2 && (
                                <button
                                    type="button"
                                    onClick={() => removeOption(i)}
                                    className="shrink-0 px-2 py-1 font-sans text-xs text-[var(--color-accent)] hover:text-red-600"
                                >
                                    Remove
                                </button>
                            )}
                        </div>
                    ))}
                </div>
                <button
                    type="button"
                    onClick={addOption}
                    className="mt-2 font-sans text-xs text-[var(--color-accent)] hover:text-[var(--color-text)]"
                >
                    + Add option
                </button>
            </div>
        </div>
    );
}
