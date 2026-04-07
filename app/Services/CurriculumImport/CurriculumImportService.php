<?php

namespace App\Services\CurriculumImport;

use App\Jobs\ParseCurriculumImportJob;
use App\Models\CurriculumImport;
use App\Models\User;
use App\Services\CurriculumImport\Extractors\ContentExtractor;
use App\Services\CurriculumImport\Extractors\DocxExtractor;
use App\Services\CurriculumImport\Extractors\PdfExtractor;
use App\Services\CurriculumImport\Extractors\YouTubeExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CurriculumImportService
{
    /**
     * Initiate an import from an uploaded file.
     */
    public function importFile(UploadedFile $file, string $sourceType, User $user, ?string $courseId = null): CurriculumImport
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $directory = 'curriculum-imports';
        $disk = config('filesystems.default') === 'local' ? 'public' : 's3';
        $path = $file->storeAs($directory, $filename, $disk);

        $import = CurriculumImport::create([
            'course_id' => $courseId,
            'source_type' => $sourceType,
            'source_path' => $path,
            'status' => 'pending',
            'metadata' => [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'disk' => $disk,
            ],
            'created_by' => $user->id,
        ]);

        ParseCurriculumImportJob::dispatch($import);

        return $import;
    }

    /**
     * Initiate an import from a URL (e.g. YouTube).
     */
    public function importUrl(string $url, string $sourceType, User $user, ?string $courseId = null): CurriculumImport
    {
        $import = CurriculumImport::create([
            'course_id' => $courseId,
            'source_type' => $sourceType,
            'source_path' => $url,
            'status' => 'pending',
            'metadata' => [
                'url' => $url,
            ],
            'created_by' => $user->id,
        ]);

        ParseCurriculumImportJob::dispatch($import);

        return $import;
    }

    /**
     * Resolve the appropriate extractor for a source type.
     */
    public function resolveExtractor(string $sourceType): ContentExtractor
    {
        return match ($sourceType) {
            'pdf' => new PdfExtractor,
            'docx' => new DocxExtractor,
            'youtube' => new YouTubeExtractor,
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };
    }

    /**
     * Apply confirmed import structure to a course by creating modules.
     *
     * @param  array<string, mixed>  $structure
     * @return array{modules_created: int}
     */
    public function applyStructure(CurriculumImport $import, array $structure): array
    {
        $course = $import->course;
        if (! $course) {
            throw new \RuntimeException('Cannot apply structure: import has no associated course');
        }

        $existingModuleCount = $course->modules()->count();
        $modulesCreated = 0;

        foreach (($structure['modules'] ?? []) as $index => $moduleData) {
            $course->modules()->create([
                'title' => $moduleData['title'] ?? 'Untitled Module',
                'slug' => Str::slug($moduleData['title'] ?? 'module').'-'.Str::random(4),
                'description' => $moduleData['description'] ?? null,
                'content_blocks' => $moduleData['content_blocks'] ?? [],
                'sort_order' => $existingModuleCount + $index,
            ]);
            $modulesCreated++;
        }

        $import->update([
            'final_structure' => $structure,
            'status' => 'ready',
        ]);

        return ['modules_created' => $modulesCreated];
    }
}
