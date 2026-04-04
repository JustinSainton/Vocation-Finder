<?php

namespace App\Services\Jobs;

class JobNormalizerService
{
    /**
     * Normalize a job description by stripping HTML, excess whitespace, and encoding issues.
     */
    public function normalizeDescription(?string $html): ?string
    {
        if (! $html) {
            return null;
        }

        // Strip HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Normalize a company name for consistent display.
     */
    public function normalizeCompanyName(?string $name): string
    {
        if (! $name) {
            return 'Unknown Company';
        }

        // Remove common suffixes for cleaner display
        $name = preg_replace('/\s*(,?\s*(Inc\.?|LLC|Ltd\.?|Corp\.?|Co\.?|PLC|GmbH|SA|AG))\s*$/i', '', $name);

        return trim($name);
    }

    /**
     * Normalize a location string.
     */
    public function normalizeLocation(?string $location): ?string
    {
        if (! $location) {
            return null;
        }

        $location = trim($location);

        // Remove country code suffixes like ", US" or ", USA"
        $location = preg_replace('/,\s*(US|USA|United States)\s*$/i', '', $location);

        return $location ?: null;
    }

    /**
     * Detect if a job is remote from title, location, or description.
     */
    public function detectRemote(?string $title, ?string $location, ?string $description): bool
    {
        $text = strtolower(implode(' ', array_filter([$title, $location, $description])));

        $remoteIndicators = ['remote', 'work from home', 'telecommute', 'distributed', 'anywhere'];

        foreach ($remoteIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize salary values (ensure annual, handle hourly/monthly conversion).
     */
    public function normalizeSalary(?float $value, ?string $period = 'year'): ?int
    {
        if (! $value) {
            return null;
        }

        return match (strtolower($period ?? 'year')) {
            'hour', 'hourly' => (int) round($value * 2080),  // 40hr × 52wk
            'month', 'monthly' => (int) round($value * 12),
            'week', 'weekly' => (int) round($value * 52),
            default => (int) round($value),
        };
    }
}
