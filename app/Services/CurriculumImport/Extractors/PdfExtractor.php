<?php

namespace App\Services\CurriculumImport\Extractors;

use Illuminate\Support\Facades\Storage;

class PdfExtractor implements ContentExtractor
{
    public function extract(string $source, string $disk = 's3'): array
    {
        $content = Storage::disk($disk)->get($source);

        if (! $content) {
            throw new \RuntimeException("Could not read PDF at {$source} on disk {$disk}");
        }

        $text = $this->extractText($content);

        return [
            'text' => $text,
            'metadata' => [
                'source_type' => 'pdf',
                'character_count' => mb_strlen($text),
            ],
        ];
    }

    private function extractText(string $pdfContent): string
    {
        $text = '';

        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $pdfContent, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded) {
                    $stream = $decoded;
                }

                if (preg_match_all('/\((.*?)\)/s', $stream, $textMatches)) {
                    $text .= implode(' ', $textMatches[1])."\n";
                }

                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/', $tj, $parts)) {
                            $text .= implode('', $parts[1]).' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        $text = preg_replace('/[^\x20-\x7E\n\r]/', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
