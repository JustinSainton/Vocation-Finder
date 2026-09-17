<?php

namespace App\Console\Commands;

use App\Enums\VettingStatus;
use App\Models\JobListing;
use App\Support\JobVetting;
use Illuminate\Console\Command;

/**
 * Run the vetting pass over listings that have not had one.
 *
 * Scheduled rather than done inline at ingestion because the rules change: a
 * new fraud pattern is a new phrase list, and the whole table has to be able
 * to go back through it. `--all` is how a rule added on Tuesday reaches the
 * listing ingested on Monday.
 */
class VetJobListings extends Command
{
    protected $signature = 'jobs:vet {--all : Re-vet every listing, not only the unexamined ones}';

    protected $description = 'Run the spam and safeguarding vetting pass over job listings';

    public function handle(JobVetting $vetting): int
    {
        $query = JobListing::query();

        if (! $this->option('all')) {
            $query->where('vetting_status', VettingStatus::Pending);
        }

        $passed = 0;
        $rejected = 0;

        $query->chunkById(200, function ($listings) use ($vetting, &$passed, &$rejected) {
            foreach ($listings as $listing) {
                $vetting->vet($listing);

                $listing->vetting_status === VettingStatus::Passed ? $passed++ : $rejected++;
            }
        });

        $this->info("Vetted. {$passed} passed, {$rejected} rejected.");

        return self::SUCCESS;
    }
}
