<?php

namespace App\Services\Jobs\Adapters;

use App\Services\Jobs\Contracts\JobSourceAdapter;
use App\Services\Jobs\DTOs\JobListingData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TheMuseAdapter implements JobSourceAdapter
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('jobs.muse.base_url');
    }

    public function sourceKey(): string
    {
        return 'muse';
    }

    public function fetch(array $params = []): Collection
    {
        $page = $params['page'] ?? 0;
        $category = $params['category'] ?? null;
        $level = $params['level'] ?? null;
        $location = $params['location'] ?? null;

        $query = ['page' => $page];

        if ($category) {
            $query['category'] = $category;
        }
        if ($level) {
            $query['level'] = $level;
        }
        if ($location) {
            $query['location'] = $location;
        }

        $response = Http::timeout(15)
            ->retry(2, 1000)
            ->get("{$this->baseUrl}/jobs", $query);

        if (! $response->successful()) {
            Log::warning('The Muse API request failed', [
                'status' => $response->status(),
            ]);

            return collect();
        }

        $data = $response->json();
        $results = $data['results'] ?? [];

        return collect($results)->map(fn (array $item) => $this->mapToDto($item));
    }

    private function mapToDto(array $item): JobListingData
    {
        $locations = $item['locations'] ?? [];
        $locationNames = collect($locations)->pluck('name')->implode(', ');

        $isRemote = collect($locations)->contains(fn ($loc) => str_contains(strtolower($loc['name'] ?? ''), 'remote')
        );

        $description = $item['contents'] ?? null;
        $descriptionPlain = $description ? strip_tags($description) : null;

        return new JobListingData(
            title: $item['name'] ?? 'Untitled',
            companyName: $item['company']['name'] ?? 'Unknown Company',
            companyUrl: isset($item['company']['id']) ? "https://www.themuse.com/companies/{$item['company']['id']}" : null,
            location: $locationNames ?: null,
            isRemote: $isRemote,
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: 'USD',
            description: $description,
            descriptionPlain: $descriptionPlain,
            requiredSkills: null,
            source: 'muse',
            sourceId: (string) ($item['id'] ?? uniqid()),
            sourceUrl: $item['refs']['landing_page'] ?? null,
            postedAt: $item['publication_date'] ?? null,
            expiresAt: null,
            rawData: $item,
        );
    }
}
