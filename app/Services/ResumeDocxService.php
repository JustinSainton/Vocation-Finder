<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ResumeDocxService
{
    /**
     * Generate an ATS-friendly DOCX from structured resume data.
     * Requires phpoffice/phpword: composer require phpoffice/phpword
     */
    public function generate(array $resumeData, string $userId, string $resumeId): ?string
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            return null;
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        // ATS-friendly styles
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 720,   // 0.5 inch
            'marginBottom' => 720,
            'marginLeft' => 1080, // 0.75 inch
            'marginRight' => 1080,
        ]);

        $headingStyle = ['bold' => true, 'size' => 13, 'color' => '78716C'];
        $titleStyle = ['bold' => true, 'size' => 11];
        $subtitleStyle = ['size' => 10, 'color' => '78716C'];
        $bodyStyle = ['size' => 11];

        // Professional Summary
        if (! empty($resumeData['summary'])) {
            $section->addText('PROFESSIONAL SUMMARY', $headingStyle);
            $section->addText($resumeData['summary'], $bodyStyle);
            $section->addTextBreak();
        }

        // Work Experience
        if (! empty($resumeData['work'])) {
            $section->addText('WORK EXPERIENCE', $headingStyle);
            foreach ($resumeData['work'] as $entry) {
                $section->addText($entry['position'] ?? '', $titleStyle);
                $section->addText(
                    ($entry['company'] ?? '') . ' | ' . ($entry['startDate'] ?? '') . ' – ' . ($entry['endDate'] ?? 'Present'),
                    $subtitleStyle
                );
                foreach ($entry['highlights'] ?? [] as $highlight) {
                    $section->addListItem($highlight, 0, $bodyStyle);
                }
                $section->addTextBreak();
            }
        }

        // Education
        if (! empty($resumeData['education'])) {
            $section->addText('EDUCATION', $headingStyle);
            foreach ($resumeData['education'] as $entry) {
                $degree = trim(($entry['studyType'] ?? '') . ' in ' . ($entry['area'] ?? ''), ' in');
                $section->addText($degree, $titleStyle);
                $section->addText($entry['institution'] ?? '', $subtitleStyle);
                $section->addTextBreak();
            }
        }

        // Volunteer
        if (! empty($resumeData['volunteer'])) {
            $section->addText('VOLUNTEER EXPERIENCE', $headingStyle);
            foreach ($resumeData['volunteer'] as $entry) {
                $section->addText($entry['position'] ?? '', $titleStyle);
                $section->addText(
                    ($entry['organization'] ?? '') . ' | ' . ($entry['startDate'] ?? '') . ' – ' . ($entry['endDate'] ?? 'Present'),
                    $subtitleStyle
                );
                foreach ($entry['highlights'] ?? [] as $highlight) {
                    $section->addListItem($highlight, 0, $bodyStyle);
                }
                $section->addTextBreak();
            }
        }

        // Skills
        if (! empty($resumeData['skills'])) {
            $section->addText('SKILLS', $headingStyle);
            foreach ($resumeData['skills'] as $group) {
                $keywords = implode(', ', $group['keywords'] ?? []);
                $section->addText(($group['name'] ?? '') . ': ' . $keywords, $bodyStyle);
            }
        }

        // Save to temp file then upload to S3
        $tempPath = storage_path("app/temp-resume-{$resumeId}.docx");
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        $s3Path = "resumes/{$userId}/{$resumeId}.docx";
        Storage::disk('s3')->put($s3Path, file_get_contents($tempPath), [
            'ContentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        @unlink($tempPath);

        return $s3Path;
    }
}
