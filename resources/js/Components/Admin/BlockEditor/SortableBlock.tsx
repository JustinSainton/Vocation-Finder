import { useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { BLOCK_TYPE_LABELS, type ContentBlock } from './types';
import { TextBlockEditor } from './blocks/TextBlock';
import { ReflectionBlockEditor } from './blocks/ReflectionBlock';
import { CheckpointBlockEditor } from './blocks/CheckpointBlock';
import { VideoBlockEditor } from './blocks/VideoBlock';
import { ImageBlockEditor } from './blocks/ImageBlock';
import { PdfBlockEditor } from './blocks/PdfBlock';

interface Props {
    id: string;
    block: ContentBlock;
    index: number;
    onChange: (block: ContentBlock) => void;
    onRemove: () => void;
    onDuplicate: () => void;
    /** If defined, shows a personalization prompt input for this block */
    personalizationPrompt?: string;
    onPersonalizationPromptChange?: (prompt: string) => void;
}

export function SortableBlock({ id, block, index, onChange, onRemove, onDuplicate, personalizationPrompt, onPersonalizationPromptChange }: Props) {
    const showPersonalization = personalizationPrompt !== undefined && !!onPersonalizationPromptChange;
    const [promptExpanded, setPromptExpanded] = useState(!!personalizationPrompt);

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div ref={setNodeRef} style={style} className="group">
            <div className="border border-[var(--color-divider)] bg-[var(--color-background)]">
                {/* Block header */}
                <div className="flex items-center gap-2 border-b border-[var(--color-divider)] px-3 py-2">
                    {/* Drag handle */}
                    <button
                        type="button"
                        className="cursor-grab touch-none text-[var(--color-accent)] hover:text-[var(--color-text)]"
                        {...attributes}
                        {...listeners}
                    >
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                            <circle cx="5" cy="3" r="1.5" />
                            <circle cx="11" cy="3" r="1.5" />
                            <circle cx="5" cy="8" r="1.5" />
                            <circle cx="11" cy="8" r="1.5" />
                            <circle cx="5" cy="13" r="1.5" />
                            <circle cx="11" cy="13" r="1.5" />
                        </svg>
                    </button>

                    <span className="font-sans text-xs uppercase tracking-wider text-[var(--color-accent)]">
                        {BLOCK_TYPE_LABELS[block.type]}
                    </span>

                    <div className="ml-auto flex items-center gap-1">
                        {showPersonalization && (
                            <button
                                type="button"
                                onClick={() => setPromptExpanded(!promptExpanded)}
                                className={`px-2 py-1 font-sans text-xs transition-opacity ${
                                    personalizationPrompt
                                        ? 'text-[var(--color-text)]'
                                        : 'text-[var(--color-accent)] opacity-0 group-hover:opacity-100'
                                }`}
                                title="Personalization prompt"
                            >
                                {personalizationPrompt ? 'Personalized' : 'Personalize'}
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onDuplicate}
                            className="px-2 py-1 font-sans text-xs text-[var(--color-accent)] opacity-0 transition-opacity hover:text-[var(--color-text)] group-hover:opacity-100"
                            title="Duplicate block"
                        >
                            Duplicate
                        </button>
                        <button
                            type="button"
                            onClick={onRemove}
                            className="px-2 py-1 font-sans text-xs text-[var(--color-accent)] opacity-0 transition-opacity hover:text-red-600 group-hover:opacity-100"
                            title="Remove block"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                {/* Block content */}
                <div className="p-4">
                    <BlockContent block={block} onChange={onChange} />
                </div>

                {/* Personalization prompt */}
                {showPersonalization && promptExpanded && (
                    <div className="border-t border-dashed border-[var(--color-divider)] bg-[var(--color-stone-100)] px-4 py-3">
                        <label className="mb-1 block font-sans text-xs text-[var(--color-accent)]">
                            Personalization instruction
                        </label>
                        <textarea
                            value={personalizationPrompt ?? ''}
                            onChange={(e) => onPersonalizationPromptChange(e.target.value)}
                            placeholder="e.g. Connect this content to the learner's primary vocational domain..."
                            rows={2}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-xs text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        />
                        <p className="mt-1 font-sans text-xs text-[var(--color-text-secondary)]">
                            Leave empty to use the default prompt for this block type.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

function BlockContent({ block, onChange }: { block: ContentBlock; onChange: (b: ContentBlock) => void }) {
    switch (block.type) {
        case 'text':
            return <TextBlockEditor block={block} onChange={onChange} />;
        case 'reflection':
            return <ReflectionBlockEditor block={block} onChange={onChange} />;
        case 'checkpoint':
            return <CheckpointBlockEditor block={block} onChange={onChange} />;
        case 'video':
            return <VideoBlockEditor block={block} onChange={onChange} />;
        case 'image':
            return <ImageBlockEditor block={block} onChange={onChange} />;
        case 'pdf':
            return <PdfBlockEditor block={block} onChange={onChange} />;
    }
}
