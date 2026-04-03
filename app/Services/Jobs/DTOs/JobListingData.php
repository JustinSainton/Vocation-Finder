<?php

namespace App\Services\Jobs\DTOs;

class JobListingData
{
    public function __construct(
        public readonly string $title,
        public readonly string $companyName,
        public readonly ?string $companyUrl,
        public readonly ?string $location,
        public readonly bool $isRemote,
        public readonly ?int $salaryMin,
        public readonly ?int $salaryMax,
        public readonly string $salaryCurrency,
        public readonly ?string $description,
        public readonly ?string $descriptionPlain,
        public readonly ?array $requiredSkills,
        public readonly string $source,
        public readonly string $sourceId,
        public readonly ?string $sourceUrl,
        public readonly ?string $postedAt,
        public readonly ?string $expiresAt,
        public readonly array $rawData = [],
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'company_name' => $this->companyName,
            'company_url' => $this->companyUrl,
            'location' => $this->location,
            'is_remote' => $this->isRemote,
            'salary_min' => $this->salaryMin,
            'salary_max' => $this->salaryMax,
            'salary_currency' => $this->salaryCurrency,
            'description' => $this->description,
            'description_plain' => $this->descriptionPlain,
            'required_skills' => $this->requiredSkills,
            'source' => $this->source,
            'source_id' => $this->sourceId,
            'source_url' => $this->sourceUrl,
            'posted_at' => $this->postedAt,
            'expires_at' => $this->expiresAt,
            'raw_data' => $this->rawData,
            'last_seen_at' => now(),
            'classification_status' => 'pending',
        ];
    }
}
