<?php

namespace App\Services\CurriculumImport\Extractors;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocxExtractor implements ContentExtractor
{
    public function extract(string $source, string $disk = 's3'): array
    {
        $content = Storage::disk($disk)->get($source);

        if (! $content) {
            throw new \RuntimeException("Could not read DOCX at {$source} on disk {$disk}");
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempFile, $content);

        try {
            $phpWord = IOFactory::load($tempFile, 'Word2007');
            $text = $this->extractFromDocument($phpWord);
        } finally {
            @unlink($tempFile);
        }

        return [
            'text' => $text,
            'metadata' => [
                'source_type' => 'docx',
                'character_count' => mb_strlen($text),
            ],
        ];
    }

    private function extractFromDocument(PhpWord $phpWord): string
    {
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractFromElement($element);
            }
        }

        return trim($text);
    }

    private function extractFromElement(mixed $element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                $text .= $elementText."\n";
            } elseif (is_object($elementText) && method_exists($elementText, 'getText')) {
                $text .= $elementText->getText()."\n";
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractFromElement($child);
            }
        }

        return $text;
    }
}
