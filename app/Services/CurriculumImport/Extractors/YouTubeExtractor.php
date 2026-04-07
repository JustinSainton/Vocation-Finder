<?php

namespace App\Services\CurriculumImport\Extractors;

use Illuminate\Support\Facades\Http;

class YouTubeExtractor implements ContentExtractor
{
    /**
     * Extract transcript from a YouTube video URL.
     * The $source parameter is the YouTube URL (not a file path).
     * The $disk parameter is unused for YouTube.
     */
    public function extract(string $source, string $disk = 's3'): array
    {
        $videoId = $this->parseVideoId($source);

        if (! $videoId) {
            throw new \RuntimeException("Could not parse YouTube video ID from: {$source}");
        }

        $transcript = $this->fetchTranscript($videoId);
        $metadata = $this->fetchMetadata($videoId);

        return [
            'text' => $transcript,
            'metadata' => array_merge($metadata, [
                'source_type' => 'youtube',
                'video_id' => $videoId,
                'video_url' => $source,
                'character_count' => mb_strlen($transcript),
            ]),
        ];
    }

    private function parseVideoId(string $url): ?string
    {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function fetchTranscript(string $videoId): string
    {
        // Fetch the video page to extract caption track URLs
        $response = Http::withHeaders([
            'Accept-Language' => 'en',
        ])->get("https://www.youtube.com/watch?v={$videoId}");

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch YouTube page for video {$videoId}");
        }

        $html = $response->body();

        // Extract captions JSON from the page
        if (! preg_match('/"captions":\s*(\{.*?"playerCaptionsTracklistRenderer".*?\})\s*,\s*"videoDetails"/', $html, $matches)) {
            // Fallback: try without the trailing context
            if (! preg_match('/"captionTracks":\s*(\[.*?\])/', $html, $matches)) {
                return '[No transcript available for this video]';
            }
        }

        $captionData = $matches[1];

        // Find the English caption track URL
        if (! preg_match('/"baseUrl"\s*:\s*"(https:[^"]*)"/', $captionData, $urlMatch)) {
            return '[No transcript available for this video]';
        }

        $captionUrl = str_replace('\u0026', '&', $urlMatch[1]);
        $captionResponse = Http::get($captionUrl);

        if (! $captionResponse->successful()) {
            return '[Failed to fetch transcript]';
        }

        return $this->parseTranscriptXml($captionResponse->body());
    }

    private function parseTranscriptXml(string $xml): string
    {
        $lines = [];

        try {
            $doc = new \SimpleXMLElement($xml);
            foreach ($doc->text as $textElement) {
                $text = html_entity_decode(strip_tags((string) $textElement), ENT_QUOTES, 'UTF-8');
                $text = trim($text);
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        } catch (\Throwable) {
            // Fallback: regex extraction
            if (preg_match_all('/<text[^>]*>(.*?)<\/text>/s', $xml, $matches)) {
                foreach ($matches[1] as $text) {
                    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
                    $text = trim($text);
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }
        }

        return implode(' ', $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMetadata(string $videoId): array
    {
        // Use oEmbed API (no API key required)
        $response = Http::get('https://www.youtube.com/oembed', [
            'url' => "https://www.youtube.com/watch?v={$videoId}",
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            return ['title' => '', 'author' => ''];
        }

        $data = $response->json();

        return [
            'title' => $data['title'] ?? '',
            'author' => $data['author_name'] ?? '',
        ];
    }
}
