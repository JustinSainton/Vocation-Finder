<?php

namespace App\Services\CurriculumImport\Extractors;

interface ContentExtractor
{
    /**
     * Extract text content from the given source.
     *
     * @return array{text: string, metadata: array<string, mixed>}
     */
    public function extract(string $source, string $disk = 's3'): array;
}
