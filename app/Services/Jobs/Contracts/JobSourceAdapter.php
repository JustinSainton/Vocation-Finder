<?php

namespace App\Services\Jobs\Contracts;

use Illuminate\Support\Collection;

interface JobSourceAdapter
{
    /**
     * Fetch job listings from the source.
     *
     * @param  array  $params  Source-specific search parameters
     * @return Collection<int, \App\Services\Jobs\DTOs\JobListingData>
     */
    public function fetch(array $params = []): Collection;

    /**
     * Unique key identifying this source.
     */
    public function sourceKey(): string;
}
