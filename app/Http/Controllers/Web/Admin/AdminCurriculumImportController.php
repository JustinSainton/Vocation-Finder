<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumImport;
use App\Services\CurriculumImport\CurriculumImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCurriculumImportController extends Controller
{
    public function __construct(
        private CurriculumImportService $importService,
    ) {}

    /**
     * Start a new curriculum import from a file or URL.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => ['required', 'string', 'in:pdf,docx,youtube'],
            'file' => ['required_unless:source_type,youtube', 'file', 'max:51200'],
            'url' => ['required_if:source_type,youtube', 'nullable', 'url'],
            'course_id' => ['nullable', 'uuid', 'exists:courses,id'],
        ]);

        $sourceType = $request->input('source_type');
        $courseId = $request->input('course_id');

        if ($sourceType === 'youtube') {
            $import = $this->importService->importUrl(
                $request->input('url'),
                $sourceType,
                $request->user(),
                $courseId,
            );
        } else {
            $import = $this->importService->importFile(
                $request->file('file'),
                $sourceType,
                $request->user(),
                $courseId,
            );
        }

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
        ], 201);
    }

    /**
     * Check the status of an import (for polling).
     */
    public function show(CurriculumImport $curriculumImport): JsonResponse
    {
        $data = [
            'id' => $curriculumImport->id,
            'status' => $curriculumImport->status,
            'source_type' => $curriculumImport->source_type,
            'error_message' => $curriculumImport->error_message,
        ];

        if ($curriculumImport->isReady()) {
            $data['proposed_structure'] = $curriculumImport->proposed_structure;
        }

        return response()->json($data);
    }

    /**
     * Confirm and apply the (possibly edited) import structure to the course.
     */
    public function confirm(Request $request, CurriculumImport $curriculumImport): JsonResponse
    {
        $request->validate([
            'course_id' => ['required', 'uuid', 'exists:courses,id'],
            'structure' => ['required', 'array'],
            'structure.modules' => ['required', 'array', 'min:1'],
            'structure.modules.*.title' => ['required', 'string'],
            'structure.modules.*.content_blocks' => ['present', 'array'],
        ]);

        if (! $curriculumImport->isReady()) {
            return response()->json(['error' => 'Import is not ready for confirmation'], 422);
        }

        // Associate with course if not already
        if (! $curriculumImport->course_id) {
            $curriculumImport->update(['course_id' => $request->input('course_id')]);
            $curriculumImport->refresh();
        }

        $result = $this->importService->applyStructure(
            $curriculumImport,
            $request->input('structure'),
        );

        return response()->json([
            'success' => true,
            'modules_created' => $result['modules_created'],
        ]);
    }
}
