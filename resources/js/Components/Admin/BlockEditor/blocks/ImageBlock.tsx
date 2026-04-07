import { useRef, useState } from 'react';
import { useMediaUpload } from '../useMediaUpload';
import type { ImageBlock } from '../types';

interface Props {
    block: ImageBlock;
    onChange: (block: ImageBlock) => void;
}

export function ImageBlockEditor({ block, onChange }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { uploading, progress, error, upload } = useMediaUpload();
    const [dragOver, setDragOver] = useState(false);

    const handleFile = async (file: File) => {
        if (!file.type.startsWith('image/')) return;
        const result = await upload(file);
        if (result) {
            onChange({ ...block, media_id: result.id, url: result.url });
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

    const hasImage = block.url || block.media_id;

    return (
        <div className="space-y-3">
            {hasImage ? (
                <div className="relative">
                    <img
                        src={block.url}
                        alt={block.alt || 'Uploaded image'}
                        className="max-h-64 border border-[var(--color-divider)] object-contain"
                    />
                    <button
                        type="button"
                        onClick={() => onChange({ ...block, media_id: null, url: '' })}
                        className="absolute right-2 top-2 bg-[var(--color-background)] px-2 py-1 font-sans text-xs text-[var(--color-accent)] shadow-sm hover:text-red-600"
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
                                Drag an image here or click to upload
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
                        accept="image/*"
                        onChange={handleFileInput}
                        className="hidden"
                    />
                </div>
            )}

            {error && (
                <p className="font-sans text-xs text-red-600">{error}</p>
            )}

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                        Image URL
                    </label>
                    <input
                        type="url"
                        value={block.url}
                        onChange={(e) => onChange({ ...block, url: e.target.value, media_id: null })}
                        placeholder="Or paste an image URL..."
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </div>
                <div>
                    <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                        Alt text
                    </label>
                    <input
                        type="text"
                        value={block.alt}
                        onChange={(e) => onChange({ ...block, alt: e.target.value })}
                        placeholder="Describe the image"
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </div>
            </div>
        </div>
    );
}
