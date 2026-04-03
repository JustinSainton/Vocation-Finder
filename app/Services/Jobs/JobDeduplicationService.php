<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use App\Services\Jobs\DTOs\JobListingData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JobDeduplicationService
{
    /**
     * Filter out DTOs that already exist in the database.
     * Uses source+source_id for exact match, then a normalized hash for cross-source dedup.
     *
     * @param  Collection<int, JobListingData>  $listings
     * @return Collection<int, JobListingData>
     */
    public function deduplicate(Collection $listings): Collection
    {
        if ($listings->isEmpty()) {
            return $listings;
        }

        // Exact match: same source + source_id already exists
        $existingSourceIds = JobListing::whereIn('source', $listings->pluck('source')->unique())
            ->whereIn('source_id', $listings->pluck('sourceId'))
            ->get(['source', 'source_id', 'id'])
            ->map(fn ($j) => "{$j->source}:{$j->source_id}")
            ->toArray();

        $listings = $listings->filter(function (JobListingData $dto) use ($existingSourceIds) {
            $key = "{$dto->source}:{$dto->sourceId}";

            if (in_array($key, $existingSourceIds)) {
                // Update last_seen_at for existing listings
                JobListing::where('source', $dto->source)
                    ->where('source_id', $dto->sourceId)
                    ->update(['last_seen_at' => now()]);

                return false;
            }

            return true;
        });

        // Cross-source dedup: same job posted on multiple boards
        $existingHashes = JobListing::query()
            ->select('company_name', 'title', 'location')
            ->where('created_at', '>', now()->subDays(7))
            ->get()
            ->map(fn ($j) => self::hash($j->company_name, $j->title, $j->location))
            ->toArray();

        return $listings->filter(function (JobListingData $dto) use (&$existingHashes) {
            $hash = self::hash($dto->companyName, $dto->title, $dto->location);

            if (in_array($hash, $existingHashes)) {
                return false;
            }

            $existingHashes[] = $hash;

            return true;
        })->values();
    }

    public static function hash(?string $company, ?string $title, ?string $location): string
    {
        $normalize = fn (?string $s) => Str::lower(
            trim(preg_replace('/\s+/', ' ', $s ?? ''))
        );

        return md5(implode('|', [
            $normalize($company),
            $normalize($title),
            $normalize($location),
        ]));
    }
}
