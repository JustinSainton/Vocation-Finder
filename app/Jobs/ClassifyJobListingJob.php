<?php

namespace App\Jobs;

use App\Ai\Agents\JobClassifierAgent;
use App\Models\JobListing;
use App\Models\VocationalCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyJobListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 2;

    public function __construct(
        public JobListing $jobListing,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(): void
    {
        $description = $this->jobListing->description_plain
            ?? $this->jobListing->description
            ?? '';

        $agent = new JobClassifierAgent(
            jobTitle: $this->jobListing->title,
            jobDescription: $description,
        );

        $model = config('jobs.ingestion.classification_model');
        $response = $agent->prompt($agent->buildPrompt(), model: $model);
        $result = $response->json();

        if (! is_array($result) || ! isset($result['soc_code'])) {
            Log::warning('Job classification returned invalid result', [
                'job_id' => $this->jobListing->id,
                'result' => $result,
            ]);
            $this->jobListing->update(['classification_status' => 'failed']);

            return;
        }

        $this->jobListing->update([
            'soc_code' => $result['soc_code'],
            'classification_status' => 'classified',
        ]);

        // Attach vocational categories
        $categories = VocationalCategory::all()->keyBy('slug');
        $syncData = [];

        foreach ($result['categories'] ?? [] as $cat) {
            $slug = $cat['slug'] ?? null;
            $relevance = $cat['relevance'] ?? 0.5;

            if ($slug && $categories->has($slug)) {
                $syncData[$categories->get($slug)->id] = [
                    'relevance_score' => $relevance,
                ];
            }
        }

        if (! empty($syncData)) {
            $this->jobListing->vocationalCategories()->sync($syncData);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job classification failed', [
            'job_id' => $this->jobListing->id,
            'error' => $exception->getMessage(),
        ]);

        $this->jobListing->update(['classification_status' => 'failed']);
    }
}
