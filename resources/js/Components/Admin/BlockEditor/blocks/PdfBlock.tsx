import { useRef, useState } from 'react';
import { useMediaUpload } from '../useMediaUpload';
import type { PdfBlock } from '../types';

interface Props {
    block: PdfBlock;
    onChange: (block: PdfBlock) => void;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function PdfBlockEditor({ block, onChange }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { uploading, progress, error, upload } = useMediaUpload();
    const [dragOver, setDragOver] = useState(false);
    const [fileSize, setFileSize] = useState<number | null>(null);

    const handleFile = async (file: File) => {
        if (file.type !== 'application/pdf') return;
        setFileSize(file.size);
        const result = await upload(file);
        if (result) {
            onChange({
                ...block,
                media_id: result.id,
                url: result.url,
                title: block.title || file.name.replace(/\.pdf$/i, ''),
                original_filename: result.original_filename,
            });
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        const file = e.dataTransfer.files[0];
        if (file) handleFile(file);
    };

    const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) handleFile(file);
    };

    const hasPdf = block.url || block.media_id;

    return (
        <div className="space-y-3">
            {hasPdf ? (
                <div className="flex items-center gap-3 border border-[var(--color-divider)] bg-[var(--color-stone-100)] px-4 py-3">
                    {/* PDF icon */}
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="shrink-0 text-[var(--color-accent)]">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                    <div className="flex-1">
                        <p className="font-sans text-sm font-medium text-[var(--color-text)]">
                            {block.original_filename || 'document.pdf'}
                        </p>
                        {fileSize && (
                            <p className="font-sans text-xs text-[var(--color-text-secondary)]">
                                {formatBytes(fileSize)}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            onChange({ ...block, media_id: null, url: '', original_filename: '' });
                            setFileSize(null);
                        }}
                        className="font-sans text-xs text-[var(--color-accent)] hover:text-red-600"
                    >
                        Remove
                    </button>
                </div>
            ) : (
                <div
                    onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={handleDrop}
                    className={`flex flex-col items-center gap-2 border-2 border-dashed py-10 transition-colors ${
                        dragOver
                            ? 'border-[var(--color-text)] bg-[var(--color-stone-100)]'
                            : 'border-[var(--color-divider)]'
                    }`}
                >
                    {uploading ? (
                        <>
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                Uploading... {progress}%
                            </p>
                            <div className="h-1 w-48 bg-[var(--color-divider)]">
                                <div
                                    className="h-full bg-[var(--color-text)] transition-all"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </>
                    ) : (
                        <>
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                Drag a PDF here or click to upload
                            </p>
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                            >
                                Choose file
                            </button>
                        </>
                    )}
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".pdf,application/pdf"
                        onChange={handleFileInput}
                        className="hidden"
                    />
                </div>
            )}

            {error && (
                <p className="font-sans text-xs text-red-600">{error}</p>
            )}

            <div>
                <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                    Display Title
                </label>
                <input
                    type="text"
                    value={block.title}
                    onChange={(e) => onChange({ ...block, title: e.target.value })}
                    placeholder="Title shown to learners"
                    className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                />
            </div>
        </div>
    );
}
