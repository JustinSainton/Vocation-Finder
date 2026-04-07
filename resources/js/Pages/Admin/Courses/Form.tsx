import { useState, FormEvent } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { BlockEditor, type ContentBlock } from '../../../Components/Admin/BlockEditor';
import { ImportModal } from '../../../Components/Admin/ImportModal';
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
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

interface ModuleForm {
    id?: string;
    key: string;
    title: string;
    description: string;
    content_blocks: ContentBlock[];
    personalization_prompts: string[];
    sort_order: number;
}

interface CategoryTag {
    id: string;
    name: string;
    weight: number;
}

interface CourseRef {
    id: string;
    title: string;
}

interface CourseData {
    id: string;
    title: string;
    slug: string;
    description: string;
    short_description: string | null;
    content_blocks: unknown[] | null;
    vocational_category_id: string | null;
    estimated_duration: string | null;
    sort_order: number;
    is_published: boolean;
    requires_personalization: boolean;
    difficulty_level: string;
    phase_tag: string;
    prerequisite_course_ids: string[] | null;
    category_tags: CategoryTag[];
    modules: {
        id: string;
        title: string;
        slug: string;
        description: string | null;
        content_blocks: unknown[] | null;
        personalization_prompts: string[] | null;
        sort_order: number;
    }[];
}

interface Category {
    id: string;
    name: string;
}

interface Props {
    categories: Category[];
    course: CourseData | null;
    allCourses: CourseRef[];
}

let nextModuleKey = 0;
function genModuleKey(): string {
    return `mod-${Date.now()}-${nextModuleKey++}`;
}

export default function AdminCourseForm({ categories, course, allCourses }: Props) {
    const isEditing = !!course;

    const [title, setTitle] = useState(course?.title ?? '');
    const [description, setDescription] = useState(course?.description ?? '');
    const [shortDescription, setShortDescription] = useState(course?.short_description ?? '');
    const [contentBlocks, setContentBlocks] = useState<ContentBlock[]>(
        (course?.content_blocks as ContentBlock[] | null) ?? [],
    );
    const [categoryId, setCategoryId] = useState(course?.vocational_category_id ?? '');
    const [estimatedDuration, setEstimatedDuration] = useState(course?.estimated_duration ?? '');
    const [sortOrder, setSortOrder] = useState(course?.sort_order ?? 0);
    const [isPublished, setIsPublished] = useState(course?.is_published ?? false);
    const [requiresPersonalization, setRequiresPersonalization] = useState(course?.requires_personalization ?? false);
    const [difficultyLevel, setDifficultyLevel] = useState(course?.difficulty_level ?? 'foundational');
    const [phaseTag, setPhaseTag] = useState(course?.phase_tag ?? 'discovery');
    const [prerequisiteIds, setPrerequisiteIds] = useState<string[]>(course?.prerequisite_course_ids ?? []);
    const [categoryTags, setCategoryTags] = useState<CategoryTag[]>(course?.category_tags ?? []);
    const [modules, setModules] = useState<ModuleForm[]>(
        course?.modules.map((m) => ({
            id: m.id,
            key: genModuleKey(),
            title: m.title,
            description: m.description ?? '',
            content_blocks: (m.content_blocks as ContentBlock[] | null) ?? [],
            personalization_prompts: m.personalization_prompts ?? [],
            sort_order: m.sort_order,
        })) ?? [],
    );
    const [expandedModules, setExpandedModules] = useState<Set<string>>(
        new Set(modules.length > 0 ? [modules[0].key] : []),
    );
    const [submitting, setSubmitting] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);

    const moduleSensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const toggleModule = (key: string) => {
        setExpandedModules((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    const addModule = () => {
        const key = genModuleKey();
        setModules([
            ...modules,
            { key, title: '', description: '', content_blocks: [], personalization_prompts: [], sort_order: modules.length },
        ]);
        setExpandedModules((prev) => new Set(prev).add(key));
    };

    const updateModule = (index: number, updates: Partial<ModuleForm>) => {
        const updated = [...modules];
        updated[index] = { ...updated[index], ...updates };
        setModules(updated);
    };

    const removeModule = (index: number) => {
        const key = modules[index].key;
        setModules(modules.filter((_, i) => i !== index));
        setExpandedModules((prev) => {
            const next = new Set(prev);
            next.delete(key);
            return next;
        });
    };

    const handleModuleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (over && active.id !== over.id) {
            const oldIndex = modules.findIndex((m) => m.key === active.id);
            const newIndex = modules.findIndex((m) => m.key === over.id);
            const reordered = arrayMove(modules, oldIndex, newIndex).map((m, i) => ({
                ...m,
                sort_order: i,
            }));
            setModules(reordered);
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        const data = {
            title,
            description,
            short_description: shortDescription || null,
            content_blocks: contentBlocks.length > 0 ? JSON.stringify(contentBlocks) : null,
            vocational_category_id: categoryId || null,
            estimated_duration: estimatedDuration || null,
            sort_order: sortOrder,
            is_published: isPublished,
            requires_personalization: requiresPersonalization,
            difficulty_level: difficultyLevel,
            phase_tag: phaseTag,
            prerequisite_course_ids: prerequisiteIds.length > 0 ? prerequisiteIds : null,
            category_tags: categoryTags.map((t) => ({ id: t.id, weight: t.weight })),
            modules: modules.map((m) => ({
                id: m.id || undefined,
                title: m.title,
                description: m.description || null,
                content_blocks: m.content_blocks.length > 0 ? JSON.stringify(m.content_blocks) : null,
                personalization_prompts: m.personalization_prompts.some((p) => p.trim())
                    ? JSON.stringify(m.personalization_prompts)
                    : null,
                sort_order: m.sort_order,
            })),
        };

        if (isEditing) {
            router.put(`/admin/courses/${course.slug}`, data, {
                onFinish: () => setSubmitting(false),
            });
        } else {
            router.post('/admin/courses', data, {
                onFinish: () => setSubmitting(false),
            });
        }
    };

    return (
        <AdminLayout title={isEditing ? `Edit ${course.title}` : 'New Course'}>
            <h1 className="mb-8 font-serif text-2xl text-[var(--color-text)]">
                {isEditing ? 'Edit Course' : 'New Course'}
            </h1>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Title */}
                <FieldGroup label="Title">
                    <input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        required
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </FieldGroup>

                {/* Description */}
                <FieldGroup label="Description">
                    <textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        required
                        rows={4}
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </FieldGroup>

                {/* Short description */}
                <FieldGroup label="Short Description">
                    <input
                        type="text"
                        value={shortDescription}
                        onChange={(e) => setShortDescription(e.target.value)}
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </FieldGroup>

                {/* Row: category + duration + sort order */}
                <div className="grid grid-cols-3 gap-4">
                    <FieldGroup label="Category">
                        <select
                            value={categoryId}
                            onChange={(e) => setCategoryId(e.target.value)}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="">None</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </FieldGroup>

                    <FieldGroup label="Estimated Duration">
                        <input
                            type="text"
                            value={estimatedDuration}
                            onChange={(e) => setEstimatedDuration(e.target.value)}
                            placeholder="e.g. 2 hours"
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        />
                    </FieldGroup>

                    <FieldGroup label="Sort Order">
                        <input
                            type="number"
                            value={sortOrder}
                            onChange={(e) => setSortOrder(Number(e.target.value))}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        />
                    </FieldGroup>
                </div>

                {/* Curriculum metadata */}
                <div className="grid grid-cols-2 gap-4">
                    <FieldGroup label="Difficulty Level">
                        <select
                            value={difficultyLevel}
                            onChange={(e) => setDifficultyLevel(e.target.value)}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="foundational">Foundational</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </FieldGroup>

                    <FieldGroup label="Phase Tag">
                        <select
                            value={phaseTag}
                            onChange={(e) => setPhaseTag(e.target.value)}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="discovery">Discovery</option>
                            <option value="deepening">Deepening</option>
                            <option value="integration">Integration</option>
                            <option value="application">Application</option>
                        </select>
                    </FieldGroup>
                </div>

                {/* Multi-category tags */}
                <FieldGroup label="Category Tags (with relevance weights)">
                    <div className="space-y-2">
                        {categoryTags.map((tag, i) => (
                            <div key={tag.id} className="flex items-center gap-3">
                                <span className="flex-1 font-sans text-sm text-[var(--color-text)]">
                                    {tag.name}
                                </span>
                                <input
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="0.05"
                                    value={tag.weight}
                                    onChange={(e) => {
                                        const updated = [...categoryTags];
                                        updated[i] = { ...updated[i], weight: Number(e.target.value) };
                                        setCategoryTags(updated);
                                    }}
                                    className="w-20 border border-[var(--color-divider)] bg-[var(--color-background)] px-2 py-1 font-mono text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                />
                                <button
                                    type="button"
                                    onClick={() => setCategoryTags(categoryTags.filter((_, j) => j !== i))}
                                    className="font-sans text-xs text-[var(--color-stone-400)] hover:text-[var(--color-text)]"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                        <select
                            value=""
                            onChange={(e) => {
                                const cat = categories.find((c) => c.id === e.target.value);
                                if (cat && !categoryTags.some((t) => t.id === cat.id)) {
                                    setCategoryTags([...categoryTags, { id: cat.id, name: cat.name, weight: 1.0 }]);
                                }
                            }}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-sans text-sm text-[var(--color-text-secondary)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="">+ Add category tag...</option>
                            {categories
                                .filter((c) => !categoryTags.some((t) => t.id === c.id))
                                .map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                        </select>
                    </div>
                </FieldGroup>

                {/* Prerequisites */}
                <FieldGroup label="Prerequisites">
                    <div className="space-y-2">
                        {prerequisiteIds.map((pid) => {
                            const prereq = allCourses.find((c) => c.id === pid);
                            return (
                                <div key={pid} className="flex items-center justify-between">
                                    <span className="font-sans text-sm text-[var(--color-text)]">
                                        {prereq?.title ?? pid}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setPrerequisiteIds(prerequisiteIds.filter((id) => id !== pid))}
                                        className="font-sans text-xs text-[var(--color-stone-400)] hover:text-[var(--color-text)]"
                                    >
                                        Remove
                                    </button>
                                </div>
                            );
                        })}
                        <select
                            value=""
                            onChange={(e) => {
                                if (e.target.value && !prerequisiteIds.includes(e.target.value)) {
                                    setPrerequisiteIds([...prerequisiteIds, e.target.value]);
                                }
                            }}
                            className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-sans text-sm text-[var(--color-text-secondary)] outline-none focus:border-[var(--color-text)]"
                        >
                            <option value="">+ Add prerequisite...</option>
                            {allCourses
                                .filter((c) => !prerequisiteIds.includes(c.id))
                                .map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.title}
                                    </option>
                                ))}
                        </select>
                    </div>
                </FieldGroup>

                {/* Course-level content blocks */}
                <BlockEditor
                    value={contentBlocks}
                    onChange={setContentBlocks}
                    label="Course Content Blocks"
                />

                {/* Toggles */}
                <div className="flex gap-6">
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={isPublished}
                            onChange={(e) => setIsPublished(e.target.checked)}
                            className="h-4 w-4 accent-[var(--color-text)]"
                        />
                        <span className="font-sans text-sm text-[var(--color-text)]">Published</span>
                    </label>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={requiresPersonalization}
                            onChange={(e) => setRequiresPersonalization(e.target.checked)}
                            className="h-4 w-4 accent-[var(--color-text)]"
                        />
                        <div>
                            <span className="font-sans text-sm text-[var(--color-text)]">Enable Personalization</span>
                            {requiresPersonalization && (
                                <p className="font-sans text-xs text-[var(--color-text-secondary)]">
                                    Each module block will show a prompt field for AI personalization instructions.
                                </p>
                            )}
                        </div>
                    </label>
                </div>

                {/* Modules section */}
                <div className="border-t border-[var(--color-divider)] pt-6">
                    <div className="mb-4 flex items-center justify-between">
                        <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            Modules
                        </p>
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setImportModalOpen(true)}
                                className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                            >
                                Import content
                            </button>
                            <span className="text-[var(--color-divider)]">|</span>
                            <button
                                type="button"
                                onClick={addModule}
                                className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                            >
                                + Add module
                            </button>
                        </div>
                    </div>

                    {modules.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border border-dashed border-[var(--color-divider)] py-10">
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                No modules yet. Add a module to start building your curriculum.
                            </p>
                            <button
                                type="button"
                                onClick={addModule}
                                className="bg-[var(--color-text)] px-4 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                            >
                                Add first module
                            </button>
                        </div>
                    ) : (
                        <DndContext
                            sensors={moduleSensors}
                            collisionDetection={closestCenter}
                            onDragEnd={handleModuleDragEnd}
                        >
                            <SortableContext
                                items={modules.map((m) => m.key)}
                                strategy={verticalListSortingStrategy}
                            >
                                <div className="space-y-3">
                                    {modules.map((mod, index) => (
                                        <SortableModule
                                            key={mod.key}
                                            module={mod}
                                            index={index}
                                            expanded={expandedModules.has(mod.key)}
                                            onToggle={() => toggleModule(mod.key)}
                                            onUpdate={(updates) => updateModule(index, updates)}
                                            onRemove={() => removeModule(index)}
                                            showPersonalization={requiresPersonalization}
                                        />
                                    ))}
                                </div>
                            </SortableContext>
                        </DndContext>
                    )}
                </div>

                {/* Submit */}
                <div className="flex gap-3 pt-4">
                    <button
                        type="submit"
                        disabled={submitting}
                        className="bg-[var(--color-text)] px-8 py-3 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:opacity-50"
                    >
                        {submitting ? 'Saving...' : isEditing ? 'Update Course' : 'Create Course'}
                    </button>
                    <a
                        href="/admin/courses"
                        className="border border-[var(--color-divider)] px-8 py-3 font-sans text-sm text-[var(--color-text)]"
                    >
                        Cancel
                    </a>
                </div>
            </form>

            <ImportModal
                open={importModalOpen}
                onClose={() => setImportModalOpen(false)}
                courseId={course?.id ?? null}
                onConfirm={(structure) => {
                    // Add imported modules to the form
                    const newModules = structure.modules.map((mod, i) => ({
                        key: genModuleKey(),
                        title: mod.title,
                        description: mod.description || '',
                        content_blocks: mod.content_blocks,
                        personalization_prompts: [],
                        sort_order: modules.length + i,
                    }));
                    setModules([...modules, ...newModules]);
                    // Expand first imported module
                    if (newModules.length > 0) {
                        setExpandedModules((prev) => new Set(prev).add(newModules[0].key));
                    }
                    setImportModalOpen(false);
                }}
            />
        </AdminLayout>
    );
}

/* ─── Sortable Module Accordion ─────────────────────────── */

interface SortableModuleProps {
    module: ModuleForm;
    index: number;
    expanded: boolean;
    onToggle: () => void;
    onUpdate: (updates: Partial<ModuleForm>) => void;
    onRemove: () => void;
    showPersonalization: boolean;
}

function SortableModule({ module, index, expanded, onToggle, onUpdate, onRemove, showPersonalization }: SortableModuleProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: module.key });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    const blockCount = module.content_blocks.length;

    return (
        <div ref={setNodeRef} style={style}>
            <div className="border border-[var(--color-divider)] bg-[var(--color-surface)]">
                {/* Module header — always visible */}
                <div className="flex items-center gap-3 px-4 py-3">
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

                    {/* Expand/collapse toggle */}
                    <button
                        type="button"
                        onClick={onToggle}
                        className="flex flex-1 items-center gap-2 text-left"
                    >
                        <svg
                            width="12"
                            height="12"
                            viewBox="0 0 12 12"
                            fill="currentColor"
                            className={`shrink-0 text-[var(--color-accent)] transition-transform ${expanded ? 'rotate-90' : ''}`}
                        >
                            <path d="M4 2l4 4-4 4" />
                        </svg>
                        <span className="font-sans text-sm font-medium text-[var(--color-text)]">
                            {module.title || `Module ${index + 1}`}
                        </span>
                        {!expanded && blockCount > 0 && (
                            <span className="font-sans text-xs text-[var(--color-text-secondary)]">
                                {blockCount} block{blockCount !== 1 ? 's' : ''}
                            </span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={onRemove}
                        className="font-sans text-xs text-[var(--color-accent)] hover:text-red-600"
                    >
                        Remove
                    </button>
                </div>

                {/* Module body — collapsible */}
                {expanded && (
                    <div className="space-y-4 border-t border-[var(--color-divider)] px-4 py-4">
                        <div className="grid grid-cols-1 gap-3">
                            <input
                                type="text"
                                value={module.title}
                                onChange={(e) => onUpdate({ title: e.target.value })}
                                placeholder="Module title"
                                required
                                className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                            />

                            <textarea
                                value={module.description}
                                onChange={(e) => onUpdate({ description: e.target.value })}
                                placeholder="Module description"
                                rows={2}
                                className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-serif text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                            />
                        </div>

                        <BlockEditor
                            value={module.content_blocks}
                            onChange={(blocks) => onUpdate({ content_blocks: blocks })}
                            label="Module Content"
                            personalizationPrompts={showPersonalization ? module.personalization_prompts : undefined}
                            onPersonalizationChange={showPersonalization ? (prompts) => onUpdate({ personalization_prompts: prompts }) : undefined}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

/* ─── Helpers ─────────────────────────────────────────────── */

function FieldGroup({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                {label}
            </label>
            {children}
        </div>
    );
}
