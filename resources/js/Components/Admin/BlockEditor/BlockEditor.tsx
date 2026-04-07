import { useRef } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { SortableBlock } from './SortableBlock';
import { BlockToolbar } from './BlockToolbar';
import type { ContentBlock, BlockType } from './types';
import { createEmptyBlock } from './types';

interface Props {
    value: ContentBlock[];
    onChange: (blocks: ContentBlock[]) => void;
    label?: string;
    /** When set, each block shows a personalization prompt input. Array indices match content_blocks. */
    personalizationPrompts?: string[];
    onPersonalizationChange?: (prompts: string[]) => void;
}

export function BlockEditor({ value, onChange, label, personalizationPrompts, onPersonalizationChange }: Props) {
    const showPersonalization = !!personalizationPrompts && !!onPersonalizationChange;

    // Generate stable IDs for sortable items
    const blockKeys = useRef(new Map<number, string>()).current;

    const getBlockKey = (index: number): string => {
        if (!blockKeys.has(index)) {
            blockKeys.set(index, `block-${Date.now()}-${index}`);
        }
        return blockKeys.get(index)!;
    };

    // Rebuild keys when blocks change length
    const ids = value.map((_, i) => {
        if (i >= blockKeys.size) {
            blockKeys.set(i, `block-${Date.now()}-${i}-${Math.random().toString(36).slice(2, 6)}`);
        }
        return blockKeys.get(i) ?? getBlockKey(i);
    });

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (over && active.id !== over.id) {
            const oldIndex = ids.indexOf(active.id as string);
            const newIndex = ids.indexOf(over.id as string);
            const newBlocks = arrayMove(value, oldIndex, newIndex);
            const newKeys = arrayMove(ids, oldIndex, newIndex);
            blockKeys.clear();
            newKeys.forEach((key, i) => blockKeys.set(i, key));
            onChange(newBlocks);

            // Reorder prompts to match
            if (showPersonalization && personalizationPrompts) {
                const newPrompts = arrayMove([...personalizationPrompts], oldIndex, newIndex);
                onPersonalizationChange(newPrompts);
            }
        }
    };

    const addBlock = (type: BlockType) => {
        const newBlock = createEmptyBlock(type);
        const newId = `block-${Date.now()}-${value.length}-${Math.random().toString(36).slice(2, 6)}`;
        blockKeys.set(value.length, newId);
        onChange([...value, newBlock]);

        if (showPersonalization && personalizationPrompts) {
            onPersonalizationChange([...personalizationPrompts, '']);
        }
    };

    const updateBlock = (index: number, block: ContentBlock) => {
        const updated = [...value];
        updated[index] = block;
        onChange(updated);
    };

    const removeBlock = (index: number) => {
        const newBlocks = value.filter((_, i) => i !== index);
        const oldIds = [...ids];
        oldIds.splice(index, 1);
        blockKeys.clear();
        oldIds.forEach((key, i) => blockKeys.set(i, key));
        onChange(newBlocks);

        if (showPersonalization && personalizationPrompts) {
            const newPrompts = [...personalizationPrompts];
            newPrompts.splice(index, 1);
            onPersonalizationChange(newPrompts);
        }
    };

    const duplicateBlock = (index: number) => {
        const newBlocks = [...value];
        const clone = JSON.parse(JSON.stringify(value[index])) as ContentBlock;
        newBlocks.splice(index + 1, 0, clone);
        const oldIds = [...ids];
        const newId = `block-${Date.now()}-dup-${Math.random().toString(36).slice(2, 6)}`;
        oldIds.splice(index + 1, 0, newId);
        blockKeys.clear();
        oldIds.forEach((key, i) => blockKeys.set(i, key));
        onChange(newBlocks);

        if (showPersonalization && personalizationPrompts) {
            const newPrompts = [...personalizationPrompts];
            newPrompts.splice(index + 1, 0, personalizationPrompts[index] ?? '');
            onPersonalizationChange(newPrompts);
        }
    };

    const updatePrompt = (index: number, prompt: string) => {
        if (!showPersonalization || !personalizationPrompts) return;
        const updated = [...personalizationPrompts];
        // Extend array if needed
        while (updated.length <= index) updated.push('');
        updated[index] = prompt;
        onPersonalizationChange(updated);
    };

    return (
        <div>
            {label && (
                <p className="mb-2 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    {label}
                </p>
            )}

            {value.length === 0 ? (
                <div className="flex flex-col items-center gap-3 border border-dashed border-[var(--color-divider)] py-10">
                    <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                        No content blocks yet
                    </p>
                    <BlockToolbar onAdd={addBlock} />
                </div>
            ) : (
                <>
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={handleDragEnd}
                    >
                        <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                            <div className="space-y-3">
                                {value.map((block, index) => (
                                    <SortableBlock
                                        key={ids[index]}
                                        id={ids[index]}
                                        block={block}
                                        index={index}
                                        onChange={(b) => updateBlock(index, b)}
                                        onRemove={() => removeBlock(index)}
                                        onDuplicate={() => duplicateBlock(index)}
                                        personalizationPrompt={showPersonalization ? (personalizationPrompts?.[index] ?? '') : undefined}
                                        onPersonalizationPromptChange={showPersonalization ? (p) => updatePrompt(index, p) : undefined}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>

                    <div className="mt-3">
                        <BlockToolbar onAdd={addBlock} />
                    </div>
                </>
            )}
        </div>
    );
}
