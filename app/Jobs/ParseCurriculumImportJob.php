<?php

namespace App\Jobs;

use App\Ai\Agents\CurriculumParserAgent;
use App\Models\CurriculumImport;
use App\Services\CurriculumImport\CurriculumImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseCurriculumImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public CurriculumImport $import,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(CurriculumImportService $service): void
    {
        $this->import->update(['status' => 'processing']);

        try {
            $extractor = $service->resolveExtractor($this->import->source_type);

            // YouTube uses the URL directly; file-based sources use disk path
            if ($this->import->source_type === 'youtube') {
                $extracted = $extractor->extract($this->import->source_path);
            } else {
                $disk = $this->import->metadata['disk'] ?? 'public';
                $extracted = $extractor->extract($this->import->source_path, $disk);
            }

            $text = $extracted['text'];
            $metadata = array_merge($this->import->metadata ?? [], $extracted['metadata']);

            $this->import->update(['metadata' => $metadata]);

            if (empty(trim($text))) {
                $this->import->update([
                    'status' => 'failed',
                    'error_message' => 'No text content could be extracted from the source.',
                ]);

                return;
            }

            // Run AI parsing
            $agent = new CurriculumParserAgent($text, $this->import->source_type, $metadata);
            $response = $agent->prompt($agent->buildPrompt());
            $structure = $response->json();

            if (! is_array($structure) || ! isset($structure['modules'])) {
                // Try to parse as raw JSON string
                $decoded = json_decode($response->text, true);
                if (is_array($decoded) && isset($decoded['modules'])) {
                    $structure = $decoded;
                } else {
                    throw new \RuntimeException('AI response did not contain valid course structure');
                }
            }

            // Validate module structure
            foreach ($structure['modules'] as &$module) {
                if (! isset($module['title'])) {
                    $module['title'] = 'Untitled Module';
                }
                if (! isset($module['content_blocks']) || ! is_array($module['content_blocks'])) {
                    $module['content_blocks'] = [];
                }
                foreach ($module['content_blocks'] as &$block) {
                    if (! isset($block['type'])) {
                        $block['type'] = 'text';
                    }
                }
            }

            $this->import->update([
                'status' => 'ready',
                'proposed_structure' => $structure,
            ]);

        } catch (\Throwable $e) {
            Log::error('Curriculum import failed', [
                'import_id' => $this->import->id,
                'source_type' => $this->import->source_type,
                'error' => $e->getMessage(),
            ]);

            $this->import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
