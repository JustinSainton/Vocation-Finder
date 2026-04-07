import { useEffect, useRef } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { TextBlock } from '../types';

interface Props {
    block: TextBlock;
    onChange: (block: TextBlock) => void;
}

export function TextBlockEditor({ block, onChange }: Props) {
    const isInternalUpdate = useRef(false);

    const editor = useEditor({
        extensions: [StarterKit],
        content: block.content,
        editorProps: {
            attributes: {
                class: 'prose prose-stone max-w-none min-h-[120px] px-4 py-3 font-serif text-[var(--color-text)] outline-none focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => {
            isInternalUpdate.current = true;
            onChange({ ...block, content: editor.getHTML() });
        },
    });

    // Sync editor content when block.content changes externally (e.g., loading saved course)
    useEffect(() => {
        if (editor && !isInternalUpdate.current && block.content !== editor.getHTML()) {
            editor.commands.setContent(block.content, false);
        }
        isInternalUpdate.current = false;
    }, [block.content, editor]);

    if (!editor) return null;

    return (
        <div>
            {/* Toolbar */}
            <div className="mb-2 flex flex-wrap gap-1 border-b border-[var(--color-divider)] pb-2">
                <ToolbarButton
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                    title="Bold"
                >
                    <strong>B</strong>
                </ToolbarButton>
                <ToolbarButton
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                    title="Italic"
                >
                    <em>I</em>
                </ToolbarButton>
                <ToolbarButton
                    active={editor.isActive('heading', { level: 2 })}
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                    title="Heading 2"
                >
                    H2
                </ToolbarButton>
                <ToolbarButton
                    active={editor.isActive('heading', { level: 3 })}
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                    title="Heading 3"
                >
                    H3
                </ToolbarButton>
                <div className="mx-1 w-px bg-[var(--color-divider)]" />
                <ToolbarButton
                    active={editor.isActive('bulletList')}
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                    title="Bullet list"
                >
                    &bull; List
                </ToolbarButton>
                <ToolbarButton
                    active={editor.isActive('orderedList')}
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                    title="Numbered list"
                >
                    1. List
                </ToolbarButton>
                <div className="mx-1 w-px bg-[var(--color-divider)]" />
                <ToolbarButton
                    active={editor.isActive('blockquote')}
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                    title="Blockquote"
                >
                    &ldquo; Quote
                </ToolbarButton>
            </div>

            {/* Editor */}
            <div className="border border-[var(--color-divider)] bg-[var(--color-background)]">
                <EditorContent editor={editor} />
            </div>
        </div>
    );
}

function ToolbarButton({
    active,
    onClick,
    title,
    children,
}: {
    active: boolean;
    onClick: () => void;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={title}
            className={`px-2 py-1 font-sans text-xs transition-colors ${
                active
                    ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-stone-100)] hover:text-[var(--color-text)]'
            }`}
        >
            {children}
        </button>
    );
}
