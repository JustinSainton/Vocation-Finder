<?php

namespace App\Services\Jobs\Adapters;

use App\Services\Jobs\Contracts\JobSourceAdapter;
use App\Services\Jobs\DTOs\JobListingData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JSearchAdapter implements JobSourceAdapter
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('jobs.jsearch.base_url');
        $this->apiKey = config('jobs.jsearch.api_key', '');
    }

    public function sourceKey(): string
    {
        return 'jsearch';
    }

    public function fetch(array $params = []): Collection
    {
        if (empty($this->apiKey)) {
            Log::warning('JSearch API key not configured, skipping.');

            return collect();
        }

        $query = $params['query'] ?? $params['keywords'] ?? 'jobs';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;
        $remote = $params['remote'] ?? false;

        $queryParams = [
            'query' => $query,
            'page' => $page,
            'num_pages' => 1,
            'date_posted' => 'week',
        ];

        if ($remote) {
            $queryParams['remote_jobs_only'] = true;
        }

        $response = Http::timeout(15)
            ->retry(2, 2000)
            ->withHeaders([
                'X-RapidAPI-Key' => $this->apiKey,
                'X-RapidAPI-Host' => 'jsearch.p.rapidapi.com',
            ])
            ->get("{$this->baseUrl}/search", $queryParams);

        if (! $response->successful()) {
            Log::warning('JSearch API request failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return collect();
        }

        $data = $response->json();
        $results = $data['data'] ?? [];

        return collect($results)->map(fn (array $item) => $this->mapToDto($item));
    }

    private function mapToDto(array $item): JobListingData
    {
        $salaryMin = isset($item['job_min_salary']) ? (int) $item['job_min_salary'] : null;
        $salaryMax = isset($item['job_max_salary']) ? (int) $item['job_max_salary'] : null;

        $description = $item['job_description'] ?? null;
        $descriptionPlain = $description ? strip_tags($description) : null;

        $location = $item['job_city'] ?? null;
        if ($item['job_state'] ?? null) {
            $location = $location ? "{$location}, {$item['job_state']}" : $item['job_state'];
        }
        if ($item['job_country'] ?? null) {
            $location = $location ? "{$location}, {$item['job_country']}" : $item['job_country'];
        }

        $skills = [];
        foreach ($item['job_required_skills'] ?? [] as $skill) {
            if (is_string($skill)) {
                $skills[] = $skill;
            }
        }

        return new JobListingData(
            title: $item['job_title'] ?? 'Untitled',
            companyName: $item['employer_name'] ?? 'Unknown Company',
            companyUrl: $item['employer_website'] ?? null,
            location: $location,
            isRemote: (bool) ($item['job_is_remote'] ?? false),
            salaryMin: $salaryMin,
            salaryMax: $salaryMax,
            salaryCurrency: $item['job_salary_currency'] ?? 'USD',
            description: $description,
            descriptionPlain: $descriptionPlain,
            requiredSkills: ! empty($skills) ? $skills : null,
            source: 'jsearch',
            sourceId: (string) ($item['job_id'] ?? uniqid()),
            sourceUrl: $item['job_apply_link'] ?? $item['job_google_link'] ?? null,
            postedAt: $item['job_posted_at_datetime_utc'] ?? null,
            expiresAt: $item['job_offer_expiration_datetime_utc'] ?? null,
            rawData: $item,
        );
    }
}
