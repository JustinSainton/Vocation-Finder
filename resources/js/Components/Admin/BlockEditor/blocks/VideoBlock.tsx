import type { VideoBlock } from '../types';

interface Props {
    block: VideoBlock;
    onChange: (block: VideoBlock) => void;
}

function getEmbedUrl(url: string): string | null {
    // YouTube
    const ytMatch = url.match(
        /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
    );
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;

    // Vimeo
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;

    return null;
}

export function VideoBlockEditor({ block, onChange }: Props) {
    const embedUrl = getEmbedUrl(block.url);

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                        Video URL
                    </label>
                    <input
                        type="url"
                        value={block.url}
                        onChange={(e) => onChange({ ...block, url: e.target.value })}
                        placeholder="https://youtube.com/watch?v=..."
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </div>
                <div>
                    <label className="mb-1 block font-sans text-xs text-[var(--color-text-secondary)]">
                        Title
                    </label>
                    <input
                        type="text"
                        value={block.title}
                        onChange={(e) => onChange({ ...block, title: e.target.value })}
                        placeholder="Video title"
                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                    />
                </div>
            </div>

            {/* Video preview */}
            {embedUrl ? (
                <div className="aspect-video w-full border border-[var(--color-divider)] bg-black">
                    <iframe
                        src={embedUrl}
                        title={block.title || 'Video preview'}
                        className="h-full w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                    />
                </div>
            ) : block.url ? (
                <p className="py-4 text-center font-sans text-xs text-[var(--color-text-secondary)]">
                    Paste a YouTube or Vimeo URL to see a preview
                </p>
            ) : null}
        </div>
    );
}
