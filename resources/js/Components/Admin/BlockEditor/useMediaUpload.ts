import { useState, useCallback } from 'react';
import axios from 'axios';

interface UploadResult {
    id: string;
    url: string;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    type: string;
}

interface UseMediaUploadReturn {
    uploading: boolean;
    progress: number;
    error: string | null;
    upload: (file: File, courseId?: string | null) => Promise<UploadResult | null>;
}

export function useMediaUpload(): UseMediaUploadReturn {
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);

    const upload = useCallback(async (file: File, courseId?: string | null): Promise<UploadResult | null> => {
        setUploading(true);
        setProgress(0);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);
        if (courseId) {
            formData.append('course_id', courseId);
        }

        try {
            const response = await axios.post('/admin/course-media', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (e) => {
                    if (e.total) {
                        setProgress(Math.round((e.loaded / e.total) * 100));
                    }
                },
            });

            setUploading(false);
            setProgress(100);
            return response.data as UploadResult;
        } catch (err: unknown) {
            const message = axios.isAxiosError(err)
                ? err.response?.data?.message ?? 'Upload failed'
                : 'Upload failed';
            setError(message);
            setUploading(false);
            return null;
        }
    }, []);

    return { uploading, progress, error, upload };
}
