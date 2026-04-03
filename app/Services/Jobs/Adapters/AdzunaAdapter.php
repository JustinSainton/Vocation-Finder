<?php

namespace App\Services\Jobs\Adapters;

use App\Services\Jobs\Contracts\JobSourceAdapter;
use App\Services\Jobs\DTOs\JobListingData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdzunaAdapter implements JobSourceAdapter
{
    private string $baseUrl;

    private string $appId;

    private string $apiKey;

    private string $country;

    public function __construct()
    {
        $this->baseUrl = config('jobs.adzuna.base_url');
        $this->appId = config('jobs.adzuna.app_id', '');
        $this->apiKey = config('jobs.adzuna.api_key', '');
        $this->country = config('jobs.adzuna.country', 'us');
    }

    public function sourceKey(): string
    {
        return 'adzuna';
    }

    public function fetch(array $params = []): Collection
    {
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 50;
        $keywords = $params['keywords'] ?? null;
        $location = $params['location'] ?? null;
        $category = $params['category'] ?? null;

        $query = [
            'app_id' => $this->appId,
            'app_key' => $this->apiKey,
            'results_per_page' => $perPage,
            'content-type' => 'application/json',
        ];

        if ($keywords) {
            $query['what'] = $keywords;
        }
        if ($location) {
            $query['where'] = $location;
        }
        if ($category) {
            $query['category'] = $category;
        }

        $url = "{$this->baseUrl}/jobs/{$this->country}/search/{$page}";

        $response = Http::timeout(15)
            ->retry(2, 1000)
            ->get($url, $query);

        if (! $response->successful()) {
            Log::warning('Adzuna API request failed', [
                'status' => $response->status(),
                'url' => $url,
            ]);

            return collect();
        }

        $data = $response->json();
        $results = $data['results'] ?? [];

        return collect($results)->map(fn (array $item) => $this->mapToDto($item));
    }

    private function mapToDto(array $item): JobListingData
    {
        $salaryMin = isset($item['salary_min']) ? (int) $item['salary_min'] : null;
        $salaryMax = isset($item['salary_max']) ? (int) $item['salary_max'] : null;

        $description = $item['description'] ?? null;
        $descriptionPlain = $description ? strip_tags($description) : null;

        $location = $item['location']['display_name'] ?? null;

        return new JobListingData(
            title: $item['title'] ?? 'Untitled',
            companyName: $item['company']['display_name'] ?? 'Unknown Company',
            companyUrl: null,
            location: $location,
            isRemote: str_contains(strtolower($location ?? ''), 'remote'),
            salaryMin: $salaryMin,
            salaryMax: $salaryMax,
            salaryCurrency: 'USD',
            description: $description,
            descriptionPlain: $descriptionPlain,
            requiredSkills: null,
            source: 'adzuna',
            sourceId: (string) ($item['id'] ?? uniqid()),
            sourceUrl: $item['redirect_url'] ?? null,
            postedAt: $item['created'] ?? null,
            expiresAt: null,
            rawData: $item,
        );
    }
}
