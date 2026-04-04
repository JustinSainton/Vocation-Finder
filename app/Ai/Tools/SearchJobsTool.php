<?php

namespace App\Ai\Tools;

use App\Models\JobListing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchJobsTool implements Tool
{
    public function description(): string
    {
        return 'Search for job listings matching criteria like title keywords, location, or remote status. Returns job titles, companies, locations, and match information.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string('Job title keywords to search for'),
            'location' => $schema->string('City or region to filter by')->nullable(),
            'remote' => $schema->boolean('Whether to filter for remote jobs only')->nullable(),
            'limit' => $schema->number('Number of results to return (default 5)')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $query = JobListing::active()->classified()->with('vocationalCategories');

        if ($request['query']) {
            $search = $request['query'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request['location']) {
            $query->where('location', 'like', "%{$request['location']}%");
        }

        if ($request['remote']) {
            $query->where('is_remote', true);
        }

        $limit = (int) ($request['limit'] ?? 5);

        $jobs = $query->orderByDesc('posted_at')->limit($limit)->get();

        return json_encode($jobs->map(fn ($job) => [
            'id' => $job->id,
            'title' => $job->title,
            'company' => $job->company_name,
            'location' => $job->location,
            'is_remote' => $job->is_remote,
            'salary_range' => $job->salary_min && $job->salary_max
                ? '$' . round($job->salary_min / 1000) . 'k-$' . round($job->salary_max / 1000) . 'k'
                : null,
            'categories' => $job->vocationalCategories->pluck('name')->toArray(),
            'source_url' => $job->source_url,
        ])->toArray());
    }
}
