<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Promptable;

class ResumeParserService
{
    /**
     * Parse a resume PDF into structured JSON Resume format using AI.
     * Falls back to raw text extraction if AI parsing fails.
     */
    public function parse(string $pdfContent): array
    {
        $text = $this->extractText($pdfContent);

        if (empty(trim($text))) {
            return $this->emptyProfile();
        }

        return $this->aiParse($text);
    }

    /**
     * Parse from a file path (local or S3).
     */
    public function parseFromPath(string $path, string $disk = 's3'): array
    {
        $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($path);

        if (! $content) {
            throw new \RuntimeException("Could not read file at {$path} on disk {$disk}");
        }

        return $this->parse($content);
    }

    /**
     * Extract raw text from PDF content.
     */
    private function extractText(string $pdfContent): string
    {
        // Use a simple PDF text extraction approach
        // Strip binary content and extract readable text
        $text = '';

        // Try to extract text between stream/endstream markers
        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $pdfContent, $matches)) {
            foreach ($matches[1] as $stream) {
                // Filter for readable text
                $decoded = @gzuncompress($stream);
                if ($decoded) {
                    $stream = $decoded;
                }

                // Extract text between parentheses (PDF text objects)
                if (preg_match_all('/\((.*?)\)/s', $stream, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }

                // Extract text between Tj/TJ operators
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/', $tj, $parts)) {
                            $text .= implode('', $parts[1]) . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        // Clean up
        $text = preg_replace('/[^\x20-\x7E\n\r]/', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Use AI to parse resume text into structured JSON Resume format.
     */
    private function aiParse(string $text): array
    {
        try {
            $agent = new \App\Ai\Agents\ResumeParserAgent(mb_substr($text, 0, 8000));
            $response = $agent->prompt($agent->buildPrompt(), model: 'claude-haiku-4-5-20251001');
            $result = $response->json();

            if (is_array($result)) {
                return [
                    'work_history' => $result['work'] ?? [],
                    'education' => $result['education'] ?? [],
                    'skills' => $result['skills'] ?? [],
                    'certifications' => $result['certifications'] ?? [],
                    'volunteer' => $result['volunteer'] ?? [],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('AI resume parsing failed, returning empty profile', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->emptyProfile();
    }

    private function emptyProfile(): array
    {
        return [
            'work_history' => [],
            'education' => [],
            'skills' => [],
            'certifications' => [],
            'volunteer' => [],
        ];
    }
}
