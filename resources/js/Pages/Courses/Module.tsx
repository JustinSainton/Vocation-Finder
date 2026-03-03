import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface ContentBlock {
    type: string;
    content?: string;
    url?: string;
    title?: string;
    prompt?: string;
    question?: string;
    options?: string[];
}

interface ModuleData {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    content_blocks: ContentBlock[] | null;
    sort_order: number;
}

interface CourseRef {
    title: string;
    slug: string;
}

interface ModuleRef {
    title: string;
    slug: string;
}

interface Props {
    course: CourseRef;
    module: ModuleData;
    isPersonalized: boolean;
    nextModule: ModuleRef | null;
    prevModule: ModuleRef | null;
}

export default function CourseModule({ course, module, isPersonalized, nextModule, prevModule }: Props) {
    return (
        <AppLayout title={`${module.title} — ${course.title}`}>
            <Link
                href={`/courses/${course.slug}`}
                className="mb-6 inline-block font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
            >
                &larr; {course.title}
            </Link>

            <h1 className="mb-2 font-serif text-2xl tracking-tight text-[var(--color-text)]">
                {module.title}
            </h1>

            {module.description && (
                <p className="mb-6 text-[var(--color-text-secondary)]">{module.description}</p>
            )}

            {isPersonalized && (
                <p className="mb-4 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                    Personalized for you
                </p>
            )}

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {/* Render content blocks */}
            {module.content_blocks && module.content_blocks.length > 0 ? (
                <div className="space-y-8">
                    {module.content_blocks.map((block, index) => (
                        <BlockRenderer key={index} block={block} />
                    ))}
                </div>
            ) : (
                <div className="py-16 text-center">
                    <div className="mx-auto mb-4 h-8 w-8 animate-pulse bg-[var(--color-stone-300)]" />
                    <p className="text-[var(--color-text-secondary)]">
                        Your personalized content is being prepared.
                    </p>
                    <p className="mt-2 text-sm text-[var(--color-accent)]">
                        This usually takes a moment. Refresh to check.
                    </p>
                </div>
            )}

            <div className="my-8 h-px bg-[var(--color-divider)]" />

            {/* Navigation */}
            <div className="flex items-center justify-between">
                {prevModule ? (
                    <Link
                        href={`/courses/${course.slug}/modules/${prevModule.slug}`}
                        className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                    >
                        &larr; {prevModule.title}
                    </Link>
                ) : (
                    <span />
                )}

                {nextModule ? (
                    <Link
                        href={`/courses/${course.slug}/modules/${nextModule.slug}`}
                        className="bg-[var(--color-text)] px-6 py-3 font-sans text-sm tracking-wide text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                    >
                        Next: {nextModule.title} &rarr;
                    </Link>
                ) : (
                    <Link
                        href={`/courses/${course.slug}`}
                        className="bg-[var(--color-text)] px-6 py-3 font-sans text-sm tracking-wide text-[var(--color-background)]"
                    >
                        Complete course
                    </Link>
                )}
            </div>
        </AppLayout>
    );
}

function BlockRenderer({ block }: { block: ContentBlock }) {
    switch (block.type) {
        case 'text':
            return <TextBlock content={block.content ?? ''} />;
        case 'video':
            return <VideoBlock url={block.url ?? ''} title={block.title} />;
        case 'reflection':
            return <ReflectionBlock prompt={block.prompt ?? ''} />;
        case 'checkpoint':
            return (
                <CheckpointBlock
                    question={block.question ?? ''}
                    options={block.options ?? []}
                />
            );
        default:
            return null;
    }
}

function TextBlock({ content }: { content: string }) {
    return (
        <div className="prose-stone prose max-w-none">
            {content.split('\n\n').map((paragraph, i) => (
                <p
                    key={i}
                    className="mb-4 text-lg leading-relaxed text-[var(--color-text)]"
                >
                    {paragraph}
                </p>
            ))}
        </div>
    );
}

function VideoBlock({ url, title }: { url: string; title?: string }) {
    return (
        <div>
            {title && (
                <p className="mb-2 font-sans text-sm text-[var(--color-accent)]">{title}</p>
            )}
            <div className="aspect-video w-full bg-[var(--color-surface)]">
                <iframe
                    src={url}
                    title={title ?? 'Video'}
                    className="h-full w-full border-0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                />
            </div>
        </div>
    );
}

function ReflectionBlock({ prompt }: { prompt: string }) {
    const [value, setValue] = useState('');

    return (
        <div className="border-l-[3px] border-[var(--color-accent)] bg-[var(--color-surface)] p-6">
            <p className="mb-1 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Reflection
            </p>
            <p className="mb-4 font-serif text-lg text-[var(--color-text)]">{prompt}</p>
            <textarea
                value={value}
                onChange={(e) => setValue(e.target.value)}
                rows={4}
                placeholder="Write your thoughts..."
                className="w-full border-0 border-b border-[var(--color-divider)] bg-transparent px-0 py-2 font-serif text-[var(--color-text)] outline-none placeholder:text-[var(--color-stone-400)] focus:border-[var(--color-text)]"
            />
        </div>
    );
}

function CheckpointBlock({ question, options }: { question: string; options: string[] }) {
    const [selected, setSelected] = useState<string | null>(null);

    return (
        <div className="border border-[var(--color-divider)] bg-[var(--color-surface)] p-6">
            <p className="mb-1 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                Checkpoint
            </p>
            <p className="mb-4 font-serif text-lg text-[var(--color-text)]">{question}</p>
            <div className="space-y-2">
                {options.map((option) => (
                    <button
                        key={option}
                        onClick={() => setSelected(option)}
                        className={`block w-full border px-4 py-3 text-left font-sans text-sm transition-colors ${
                            selected === option
                                ? 'border-[var(--color-text)] bg-[var(--color-text)] text-[var(--color-background)]'
                                : 'border-[var(--color-divider)] text-[var(--color-text)] hover:border-[var(--color-stone-400)]'
                        }`}
                    >
                        {option}
                    </button>
                ))}
            </div>
        </div>
    );
}
