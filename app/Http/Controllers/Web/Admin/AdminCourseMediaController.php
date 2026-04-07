<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCourseMediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB max
            'course_id' => ['nullable', 'uuid', 'exists:courses,id'],
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();
        $type = $this->resolveMediaType($mimeType);

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $courseId = $request->input('course_id');
        $directory = 'course-media'.($courseId ? "/{$courseId}" : '/shared');

        $disk = config('filesystems.default') === 'local' ? 'public' : 's3';
        $path = $file->storeAs($directory, $filename, $disk);

        $media = CourseMedia::create([
            'course_id' => $courseId,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
            'type' => $type,
            'metadata' => $this->extractMetadata($file, $type),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $media->id,
            'url' => $media->getUrl(),
            'original_filename' => $media->original_filename,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'type' => $media->type,
        ], 201);
    }

    public function show(CourseMedia $courseMedia): JsonResponse
    {
        return response()->json([
            'id' => $courseMedia->id,
            'url' => $courseMedia->getUrl(),
            'original_filename' => $courseMedia->original_filename,
            'mime_type' => $courseMedia->mime_type,
            'size_bytes' => $courseMedia->size_bytes,
            'type' => $courseMedia->type,
        ]);
    }

    public function destroy(CourseMedia $courseMedia): JsonResponse
    {
        $courseMedia->deleteFile();
        $courseMedia->delete();

        return response()->json(['deleted' => true]);
    }

    private function resolveMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (in_array($mimeType, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            return 'presentation';
        }

        return 'document';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractMetadata(mixed $file, string $type): ?array
    {
        if ($type === 'image') {
            $size = @getimagesize($file->getRealPath());
            if ($size) {
                return ['width' => $size[0], 'height' => $size[1]];
            }
        }

        return null;
    }
}
