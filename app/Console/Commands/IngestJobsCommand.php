<?php

namespace App\Console\Commands;

use App\Jobs\ClassifyJobListingJob;
use App\Models\JobListing;
use App\Services\Jobs\Adapters\AdzunaAdapter;
use App\Services\Jobs\Adapters\TheMuseAdapter;
use App\Services\Jobs\Contracts\JobSourceAdapter;
use App\Services\Jobs\JobDeduplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestJobsCommand extends Command
{
    protected $signature = 'jobs:ingest
        {--source=all : Source to ingest from (adzuna, muse, all)}
        {--pages=3 : Number of pages to fetch per source}
        {--classify : Dispatch classification jobs for new listings}';

    protected $description = 'Ingest job listings from external APIs';

    public function handle(JobDeduplicationService $dedup): int
    {
        $source = $this->option('source');
        $pages = (int) $this->option('pages');
        $shouldClassify = $this->option('classify');

        $adapters = $this->resolveAdapters($source);

        if (empty($adapters)) {
            $this->error("Unknown source: {$source}");

            return self::FAILURE;
        }

        $totalNew = 0;

        foreach ($adapters as $adapter) {
            $this->info("Ingesting from {$adapter->sourceKey()}...");
            $sourceNew = 0;

            for ($page = 1; $page <= $pages; $page++) {
                $listings = $adapter->fetch(['page' => $page]);

                if ($listings->isEmpty()) {
                    $this->line("  Page {$page}: no results, stopping.");
                    break;
                }

                $unique = $dedup->deduplicate($listings);

                $this->line("  Page {$page}: {$listings->count()} fetched, {$unique->count()} new.");

                foreach ($unique as $dto) {
                    $job = JobListing::create($dto->toArray());

                    if ($shouldClassify) {
                        ClassifyJobListingJob::dispatch($job);
                    }

                    $sourceNew++;
                }
            }

            $this->info("  {$adapter->sourceKey()}: {$sourceNew} new listings ingested.");
            $totalNew += $sourceNew;
        }

        $this->info("Total: {$totalNew} new listings ingested.");

        if ($shouldClassify && $totalNew > 0) {
            $this->info("{$totalNew} classification jobs dispatched to ai-analysis queue.");
        }

        Log::info('Job ingestion completed', [
            'source' => $source,
            'new_listings' => $totalNew,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return JobSourceAdapter[]
     */
    private function resolveAdapters(string $source): array
    {
        $map = [
            'adzuna' => AdzunaAdapter::class,
            'muse' => TheMuseAdapter::class,
        ];

        if ($source === 'all') {
            return array_map(fn ($class) => new $class, $map);
        }

        if (isset($map[$source])) {
            return [new $map[$source]];
        }

        return [];
    }
}
