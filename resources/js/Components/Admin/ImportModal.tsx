import { useState, useRef, useEffect, useCallback } from 'react';
import axios from 'axios';
import { BlockEditor, type ContentBlock } from './BlockEditor';

type ImportStep = 'select' | 'uploading' | 'processing' | 'review' | 'error';

interface ProposedModule {
    title: string;
    description: string;
    content_blocks: ContentBlock[];
}

interface ProposedStructure {
    suggested_title: string;
    suggested_description: string;
    modules: ProposedModule[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    onConfirm: (structure: ProposedStructure) => void;
    courseId?: string | null;
}

const SOURCE_TYPES = [
    { value: 'pdf', label: 'PDF Document', accept: '.pdf', description: 'Upload a PDF curriculum, syllabus, or course material' },
    { value: 'docx', label: 'Word Document', accept: '.docx', description: 'Upload a .docx file from Google Docs or Microsoft Word' },
    { value: 'youtube', label: 'YouTube Video', accept: null, description: 'Import from a YouTube video transcript' },
] as const;

export function ImportModal({ open, onClose, onConfirm, courseId }: Props) {
    const [step, setStep] = useState<ImportStep>('select');
    const [sourceType, setSourceType] = useState<string>('pdf');
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const [uploadProgress, setUploadProgress] = useState(0);
    const [importId, setImportId] = useState<string | null>(null);
    const [structure, setStructure] = useState<ProposedStructure | null>(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [expandedModule, setExpandedModule] = useState<number>(0);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const resetState = useCallback(() => {
        setStep('select');
        setSourceType('pdf');
        setYoutubeUrl('');
        setUploadProgress(0);
        setImportId(null);
        setStructure(null);
        setErrorMessage('');
        setExpandedModule(0);
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    useEffect(() => {
        if (!open) resetState();
    }, [open, resetState]);

    // Cleanup polling on unmount
    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    const startPolling = useCallback((id: string) => {
        if (pollRef.current) clearInterval(pollRef.current);
        pollRef.current = setInterval(async () => {
            try {
                const res = await axios.get(`/admin/curriculum-import/${id}`);
                const data = res.data;

                if (data.status === 'ready' && data.proposed_structure) {
                    if (pollRef.current) clearInterval(pollRef.current);
                    setStructure(data.proposed_structure);
                    setStep('review');
                } else if (data.status === 'failed') {
                    if (pollRef.current) clearInterval(pollRef.current);
                    setErrorMessage(data.error_message || 'Import failed');
                    setStep('error');
                }
            } catch {
                // Keep polling on network errors
            }
        }, 2000);
    }, []);

    const handleFileUpload = async (file: File) => {
        setStep('uploading');
        setUploadProgress(0);

        const formData = new FormData();
        formData.append('source_type', sourceType);
        formData.append('file', file);
        if (courseId) formData.append('course_id', courseId);

        try {
            const res = await axios.post('/admin/curriculum-import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (e) => {
                    if (e.total) setUploadProgress(Math.round((e.loaded / e.total) * 100));
                },
            });
            setImportId(res.data.id);
            setStep('processing');
            startPolling(res.data.id);
        } catch (err: unknown) {
            const msg = axios.isAxiosError(err) ? err.response?.data?.message ?? 'Upload failed' : 'Upload failed';
            setErrorMessage(msg);
            setStep('error');
        }
    };

    const handleYoutubeSubmit = async () => {
        if (!youtubeUrl.trim()) return;
        setStep('uploading');
        setUploadProgress(100);

        try {
            const res = await axios.post('/admin/curriculum-import', {
                source_type: 'youtube',
                url: youtubeUrl,
                course_id: courseId || undefined,
            });
            setImportId(res.data.id);
            setStep('processing');
            startPolling(res.data.id);
        } catch (err: unknown) {
            const msg = axios.isAxiosError(err) ? err.response?.data?.message ?? 'Import failed' : 'Import failed';
            setErrorMessage(msg);
            setStep('error');
        }
    };

    const handleConfirm = () => {
        if (structure) onConfirm(structure);
    };

    const updateModuleBlocks = (moduleIndex: number, blocks: ContentBlock[]) => {
        if (!structure) return;
        const updated = { ...structure };
        updated.modules = [...updated.modules];
        updated.modules[moduleIndex] = { ...updated.modules[moduleIndex], content_blocks: blocks };
        setStructure(updated);
    };

    const updateModuleField = (moduleIndex: number, field: 'title' | 'description', value: string) => {
        if (!structure) return;
        const updated = { ...structure };
        updated.modules = [...updated.modules];
        updated.modules[moduleIndex] = { ...updated.modules[moduleIndex], [field]: value };
        setStructure(updated);
    };

    const removeModule = (moduleIndex: number) => {
        if (!structure) return;
        const updated = { ...structure };
        updated.modules = updated.modules.filter((_, i) => i !== moduleIndex);
        setStructure(updated);
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 pt-16 pb-16" onClick={onClose}>
            <div className="w-full max-w-3xl bg-[var(--color-background)] shadow-lg" onClick={(e) => e.stopPropagation()}>
                {/* Header */}
                <div className="flex items-center justify-between border-b border-[var(--color-divider)] px-6 py-4">
                    <h2 className="font-serif text-lg text-[var(--color-text)]">Import Content</h2>
                    <button type="button" onClick={onClose} className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]">
                        Close
                    </button>
                </div>

                <div className="px-6 py-6">
                    {/* Step: Select source */}
                    {step === 'select' && (
                        <div className="space-y-4">
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                Import existing curriculum from a file or video. AI will analyze the content and
                                propose a module structure you can review and edit before adding to your course.
                            </p>

                            <div className="space-y-2">
                                {SOURCE_TYPES.map((src) => (
                                    <label
                                        key={src.value}
                                        className={`flex cursor-pointer items-start gap-3 border p-4 transition-colors ${
                                            sourceType === src.value
                                                ? 'border-[var(--color-text)] bg-[var(--color-stone-100)]'
                                                : 'border-[var(--color-divider)] hover:border-[var(--color-accent)]'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="source_type"
                                            value={src.value}
                                            checked={sourceType === src.value}
                                            onChange={() => setSourceType(src.value)}
                                            className="mt-1 accent-[var(--color-text)]"
                                        />
                                        <div>
                                            <p className="font-sans text-sm font-medium text-[var(--color-text)]">{src.label}</p>
                                            <p className="font-sans text-xs text-[var(--color-text-secondary)]">{src.description}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>

                            {sourceType === 'youtube' ? (
                                <div className="space-y-3">
                                    <input
                                        type="url"
                                        value={youtubeUrl}
                                        onChange={(e) => setYoutubeUrl(e.target.value)}
                                        placeholder="https://youtube.com/watch?v=..."
                                        className="w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-4 py-3 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                    />
                                    <button
                                        type="button"
                                        onClick={handleYoutubeSubmit}
                                        disabled={!youtubeUrl.trim()}
                                        className="bg-[var(--color-text)] px-6 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)] disabled:opacity-50"
                                    >
                                        Import Video
                                    </button>
                                </div>
                            ) : (
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="bg-[var(--color-text)] px-6 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                    >
                                        Choose File
                                    </button>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept={SOURCE_TYPES.find((s) => s.value === sourceType)?.accept ?? '*'}
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) handleFileUpload(file);
                                        }}
                                        className="hidden"
                                    />
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step: Uploading */}
                    {step === 'uploading' && (
                        <div className="flex flex-col items-center gap-3 py-10">
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                Uploading... {uploadProgress}%
                            </p>
                            <div className="h-1 w-64 bg-[var(--color-divider)]">
                                <div className="h-full bg-[var(--color-text)] transition-all" style={{ width: `${uploadProgress}%` }} />
                            </div>
                        </div>
                    )}

                    {/* Step: Processing */}
                    {step === 'processing' && (
                        <div className="flex flex-col items-center gap-3 py-10">
                            <div className="h-8 w-8 animate-spin border-2 border-[var(--color-divider)] border-t-[var(--color-text)]" style={{ borderRadius: '50%' }} />
                            <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                AI is analyzing your content and building a course structure...
                            </p>
                            <p className="font-sans text-xs text-[var(--color-accent)]">
                                This usually takes 15-30 seconds
                            </p>
                        </div>
                    )}

                    {/* Step: Error */}
                    {step === 'error' && (
                        <div className="space-y-4 py-6">
                            <p className="font-sans text-sm text-red-600">{errorMessage}</p>
                            <button
                                type="button"
                                onClick={resetState}
                                className="font-sans text-sm text-[var(--color-accent)] hover:text-[var(--color-text)]"
                            >
                                Try again
                            </button>
                        </div>
                    )}

                    {/* Step: Review proposed structure */}
                    {step === 'review' && structure && (
                        <div className="space-y-4">
                            <div className="border-b border-[var(--color-divider)] pb-4">
                                <p className="mb-1 font-sans text-xs uppercase tracking-widest text-[var(--color-accent)]">
                                    Proposed Course Structure
                                </p>
                                <p className="font-serif text-lg text-[var(--color-text)]">
                                    {structure.suggested_title}
                                </p>
                                <p className="font-sans text-sm text-[var(--color-text-secondary)]">
                                    {structure.suggested_description}
                                </p>
                                <p className="mt-2 font-sans text-xs text-[var(--color-accent)]">
                                    {structure.modules.length} module{structure.modules.length !== 1 ? 's' : ''} generated.
                                    Review and edit below, then confirm to add to your course.
                                </p>
                            </div>

                            <div className="max-h-96 space-y-2 overflow-y-auto">
                                {structure.modules.map((mod, i) => (
                                    <div key={i} className="border border-[var(--color-divider)]">
                                        <div
                                            className="flex cursor-pointer items-center gap-2 px-4 py-3"
                                            onClick={() => setExpandedModule(expandedModule === i ? -1 : i)}
                                        >
                                            <svg
                                                width="12" height="12" viewBox="0 0 12 12" fill="currentColor"
                                                className={`shrink-0 text-[var(--color-accent)] transition-transform ${expandedModule === i ? 'rotate-90' : ''}`}
                                            >
                                                <path d="M4 2l4 4-4 4" />
                                            </svg>
                                            <input
                                                type="text"
                                                value={mod.title}
                                                onChange={(e) => { e.stopPropagation(); updateModuleField(i, 'title', e.target.value); }}
                                                onClick={(e) => e.stopPropagation()}
                                                className="flex-1 border-none bg-transparent font-sans text-sm font-medium text-[var(--color-text)] outline-none"
                                            />
                                            <span className="font-sans text-xs text-[var(--color-text-secondary)]">
                                                {mod.content_blocks.length} blocks
                                            </span>
                                            <button
                                                type="button"
                                                onClick={(e) => { e.stopPropagation(); removeModule(i); }}
                                                className="font-sans text-xs text-[var(--color-accent)] hover:text-red-600"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        {expandedModule === i && (
                                            <div className="border-t border-[var(--color-divider)] px-4 py-3">
                                                <textarea
                                                    value={mod.description}
                                                    onChange={(e) => updateModuleField(i, 'description', e.target.value)}
                                                    placeholder="Module description"
                                                    rows={2}
                                                    className="mb-3 w-full border border-[var(--color-divider)] bg-[var(--color-background)] px-3 py-2 font-sans text-sm text-[var(--color-text)] outline-none focus:border-[var(--color-text)]"
                                                />
                                                <BlockEditor
                                                    value={mod.content_blocks}
                                                    onChange={(blocks) => updateModuleBlocks(i, blocks)}
                                                />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="flex gap-3 border-t border-[var(--color-divider)] pt-4">
                                <button
                                    type="button"
                                    onClick={handleConfirm}
                                    className="bg-[var(--color-text)] px-6 py-2 font-sans text-sm text-[var(--color-background)] transition-colors hover:bg-[var(--color-stone-800)]"
                                >
                                    Add {structure.modules.length} Modules to Course
                                </button>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="border border-[var(--color-divider)] px-6 py-2 font-sans text-sm text-[var(--color-text)]"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
