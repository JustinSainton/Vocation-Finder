<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CompanyResearchTool implements Tool
{
    public function description(): string
    {
        return 'Research a company to gather information about their mission, culture, and recent activity. Use this data to personalize cover letters with the company research touch.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'company_name' => $schema->string('The name of the company to research'),
        ];
    }

    public function handle(Request $request): string
    {
        $companyName = $request['company_name'];
        $data = ['company_name' => $companyName];

        // Try The Muse API for company profile
        $slug = strtolower(str_replace(' ', '-', $companyName));
        $response = Http::timeout(10)->get("https://www.themuse.com/api/public/companies/{$slug}");

        if ($response->successful()) {
            $company = $response->json();
            $data['description'] = $company['description'] ?? null;
            $data['industries'] = collect($company['industries'] ?? [])->pluck('name')->toArray();
            $data['locations'] = collect($company['locations'] ?? [])->pluck('name')->toArray();
            $data['size'] = $company['size']['name'] ?? null;
            $data['source'] = 'themuse';
        }

        if (empty($data['description'])) {
            $data['note'] = 'Limited company data available. Use the job description for company context.';
        }

        return json_encode($data);
    }
}
