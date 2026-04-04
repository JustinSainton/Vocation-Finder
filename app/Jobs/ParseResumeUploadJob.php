<?php

namespace App\Jobs;

use App\Models\CareerProfile;
use App\Services\ResumeParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseResumeUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(
        public CareerProfile $careerProfile,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(ResumeParserService $parser): void
    {
        $rawData = $this->careerProfile->raw_import_data;
        $filePath = $rawData['file_path'] ?? null;

        if (! $filePath) {
            Log::warning('ParseResumeUploadJob: No file path found', [
                'career_profile_id' => $this->careerProfile->id,
            ]);

            return;
        }

        $parsed = $parser->parseFromPath($filePath);

        $this->careerProfile->update([
            'work_history' => $parsed['work_history'],
            'education' => $parsed['education'],
            'skills' => $parsed['skills'],
            'certifications' => $parsed['certifications'],
            'volunteer' => $parsed['volunteer'],
            'raw_import_data' => array_merge($rawData, ['parsed' => true]),
        ]);

        Log::info('Resume parsed successfully', [
            'career_profile_id' => $this->careerProfile->id,
            'work_entries' => count($parsed['work_history']),
            'education_entries' => count($parsed['education']),
            'skills' => count($parsed['skills']),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Resume parsing failed', [
            'career_profile_id' => $this->careerProfile->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
