import { useState, FormEvent } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { router } from '@inertiajs/react';

interface ModuleForm {
    id?: string;
    title: string;
    description: string;
    content_blocks: string;
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

export default function AdminCourseForm({ categories, course, allCourses }: Props) {
    const isEditing = !!course;

    const [title, setTitle] = useState(course?.title ?? '');
    const [description, setDescription] = useState(course?.description ?? '');
    const [shortDescription, setShortDescription] = useState(course?.short_description ?? '');
    const [contentBlocks, setContentBlocks] = useState(
        course?.content_blocks ? JSON.stringify(course.content_blocks, null, 2) : ''
    );
    const [categoryId, setCategoryId] = useState(course?.vocational_category_id ?? '');
    const [estimatedDuration, setEstimatedDuration] = useState(course?.estimated_duration ?? '');
    const [sortOrder, setSortOrder] = useState(course?.sort_order ?? 0);
    const [isPublished, setIsPublished] = useState(course?.is_published ?? false);
    const [difficultyLevel, setDifficultyLevel] = useState(course?.difficulty_level ?? 'foundational');
    const [phaseTag, setPhaseTag] = useState(course?.phase_tag ?? 'discovery');
    const [prerequisiteIds, setPrerequisiteIds] = useState<string[]>(course?.prerequisite_course_ids ?? []);
    const [categoryTags, setCategoryTags] = useState<{ id: string; name: string; weight: number }[]>(
        course?.category_tags ?? []
    );
    const [modules, setModules] = useState<ModuleForm[]>(
        course?.modules.map((m) => ({
            id: m.id,
            title: m.title,
            description: m.description ?? '',
            content_blocks: m.content_blocks ? JSON.stringify(m.content_blocks, null, 2) : '',
            sort_order: m.sort_order,
        })) ?? []
    );
    const [submitting, setSubmitting] = useState(false);

    const addModule = () => {
        setModules([
            ...modules,
            { title: '', description: '', content_blocks: '', sort_order: modules.length },
        ]);
    };

    const updateModule = (index: number, field: keyof ModuleForm, value: string | number) => {
        const updated = [...modules];
        (updated[index] as Record<string, string | number | undefined>)[field] = value;
        setModules(updated);
    };

    const removeModule = (index: number) => {
        setModules(modules.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        const data = {
            title,
            description,
            short_description: shortDescription || null,
            content_blocks: contentBlocks.trim() || null,
            vocational_category_id: categoryId || null,
            estimated_duration: estimatedDuration || null,
            sort_order: sortOrder,
            is_published: isPublished,
            difficulty_level: difficultyLevel,
            phase_tag: phaseTag,
            prerequisite_course_ids: prerequisiteIds.length > 0 ? prerequisiteIds : null,
            category_tags: categoryTags.map((t) => ({ id: t.id, weight: t.weight })),
            modules: modules.map((m) => ({
                id: m.id || undefined,
                title: m.title,
                description: m.description || null,
                content_blocks: m.content_blocks.trim() || null,
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

                {/* Content Blocks JSON */}
                <FieldGroup label="Content Blocks (JSON)">
                    <textarea
                        value={contentBlocks}
                        onChange={(e) => setContentBlocks(e.target.value)}
                        rows={6}
                        placeholder='[{"type": "text", "content": "..."}]'
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-mono text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </FieldGroup>

                {/* Published toggle */}
                <label className="flex items-center gap-3">
                    <input
                        type="checkbox"
                        checked={isPublished}
                        onChange={(e) => setIsPublished(e.target.checked)}
                        className="h-4 w-4 accent-[var(--color-text)]"
                    />
                    <span className="font-sans text-sm text-[var(--color-text)]">Published</span>
                </label>

                {/* Modules section */}
                <div className="border-t border-[var(--color-divider)] pt-6">
                    <div className="mb-4 flex items-center justify-between">
                        <p className="font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                            Modules
                        </p>
                        <button
                            type="button"
                            onClick={addModule}
                            className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                        >
                            + Add module
                        </button>
                    </div>

                    {modules.length === 0 && (
                        <p className="py-4 text-center text-sm text-[var(--color-text-secondary)]">
                            No modules yet.
                        </p>
                    )}

                    <div className="space-y-6">
                        {modules.map((mod, index) => (
                            <div
                                key={index}
                                className="border border-[var(--color-divider)] bg-[var(--color-surface)] p-5"
                            >
                                <div className="mb-4 flex items-center justify-between">
                                    <p className="font-sans text-sm text-[var(--color-text)]">
                                        Module {index + 1}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => removeModule(index)}
                                        className="font-sans text-xs text-[var(--color-stone-400)] hover:text-[var(--color-text)]"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    <input
                                        type="text"
                                        value={mod.title}
                                        onChange={(e) =>
                                            updateModule(index, 'title', e.target.value)
                                        }
                                        placeholder="Module title"
                                        required
                                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-serif text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                    />

                                    <textarea
                                        value={mod.description}
                                        onChange={(e) =>
                                            updateModule(index, 'description', e.target.value)
                                        }
                                        placeholder="Module description"
                                        rows={2}
                                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-serif text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                    />

                                    <textarea
                                        value={mod.content_blocks}
                                        onChange={(e) =>
                                            updateModule(
                                                index,
                                                'content_blocks',
                                                e.target.value
                                            )
                                        }
                                        placeholder='Content blocks JSON: [{"type": "text", "content": "..."}]'
                                        rows={4}
                                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-2 font-mono text-xs text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                    />

                                    <input
                                        type="number"
                                        value={mod.sort_order}
                                        onChange={(e) =>
                                            updateModule(
                                                index,
                                                'sort_order',
                                                Number(e.target.value)
                                            )
                                        }
                                        placeholder="Sort order"
                                        className="w-24 border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
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
        </AdminLayout>
    );
}

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
