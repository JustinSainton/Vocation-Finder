<?php

namespace App\Ai\Tools;

use App\Jobs\GenerateResumeJob;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GenerateResumeTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Generate a resume for the user based on their career profile and vocational assessment. Call this once you have gathered enough information about their experience, education, and skills. Optionally target a specific job listing.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_listing_id' => $schema->string('Optional UUID of a specific job listing to tailor the resume for')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $assessment = $this->user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first();

        $jobListingId = $request['job_listing_id'] ?? null;

        $resume = ResumeVersion::create([
            'user_id' => $this->user->id,
            'job_listing_id' => $jobListingId,
            'assessment_id' => $assessment?->id,
            'resume_data' => [],
            'status' => 'generating',
        ]);

        GenerateResumeJob::dispatch($resume);

        return json_encode([
            'success' => true,
            'resume_id' => $resume->id,
            'message' => 'Resume generation has started. It will be ready in about 20-30 seconds.',
        ]);
    }
}
