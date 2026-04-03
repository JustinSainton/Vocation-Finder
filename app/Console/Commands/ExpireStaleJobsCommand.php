<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use Illuminate\Console\Command;

class ExpireStaleJobsCommand extends Command
{
    protected $signature = 'jobs:expire-stale';

    protected $description = 'Soft-delete job listings that have expired or not been seen recently';

    public function handle(): int
    {
        $staleDays = config('jobs.ingestion.stale_after_days', 30);

        // Delete jobs that have an explicit expiry date in the past
        $expired = JobListing::where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->delete();

        // Delete jobs not seen by any adapter in staleDays
        $stale = JobListing::where('last_seen_at', '<', now()->subDays($staleDays))
            ->whereNull('deleted_at')
            ->delete();

        $total = $expired + $stale;

        $this->info("Removed {$expired} expired and {$stale} stale listings ({$total} total).");

        return self::SUCCESS;
    }
}
