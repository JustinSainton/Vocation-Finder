<?php

namespace App\Jobs;

use App\Ai\Agents\ResumeQualityAgent;
use App\Ai\Agents\ResumeWriterAgent;
use App\Models\ResumeVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public ResumeVersion $resumeVersion,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(): void
    {
        $this->resumeVersion->update(['status' => 'generating']);

        $user = $this->resumeVersion->user;
        $jobListing = $this->resumeVersion->jobListing;
        $assessment = $this->resumeVersion->assessment;

        $profile = $assessment?->vocationalProfile;
        $careerProfile = $user->careerProfile;
        $voiceProfile = $user->voiceProfile;

        // Build inputs
        $careerData = $careerProfile ? [
            'work_history' => $careerProfile->work_history ?? [],
            'education' => $careerProfile->education ?? [],
            'skills' => $careerProfile->skills ?? [],
            'certifications' => $careerProfile->certifications ?? [],
            'volunteer' => $careerProfile->volunteer ?? [],
        ] : [];

        $vocationalData = $profile ? [
            'primary_domain' => $profile->primary_domain,
            'primary_pathways' => $profile->primary_pathways,
            'secondary_orientation' => $profile->secondary_orientation,
            'vocational_orientation' => $profile->vocational_orientation,
            'opening_synthesis' => $profile->opening_synthesis,
        ] : [];

        $jobData = $jobListing ? [
            'title' => $jobListing->title,
            'company' => $jobListing->company_name,
            'description' => mb_substr($jobListing->description_plain ?? $jobListing->description ?? '', 0, 3000),
            'required_skills' => $jobListing->required_skills,
            'location' => $jobListing->location,
        ] : [];

        $voiceData = $voiceProfile ? [
            'tone_register' => $voiceProfile->tone_register,
            'avg_sentence_length' => $voiceProfile->avg_sentence_length,
            'preferred_verbs' => $voiceProfile->preferred_verbs,
            'banned_phrases' => $voiceProfile->banned_phrases,
            'style_summary' => $voiceProfile->style_analysis['style_summary'] ?? null,
        ] : null;

        // Detect life stage from career data
        $lifeStage = $this->detectLifeStage($careerData);

        // Pass 1: Generate resume content
        $writer = new ResumeWriterAgent(
            careerProfile: $careerData,
            vocationalProfile: $vocationalData,
            jobDescription: $jobData,
            voiceProfile: $voiceData,
            lifeStage: $lifeStage,
        );

        $response = $writer->prompt($writer->buildPrompt());
        $resumeData = $response->json();

        // Pass 2: Quality scoring
        $resumeText = $this->renderResumeAsText($resumeData);

        $quality = new ResumeQualityAgent(
            resumeText: $resumeText,
            jobTitle: $jobData['title'] ?? 'General',
        );

        $qualityResponse = $quality->prompt($quality->buildPrompt());
        $qualityResult = $qualityResponse->json();

        $score = $qualityResult['total_score'] ?? 0;

        // Quality gate: if below 70 on first attempt, regenerate once
        if ($score < 70 && $this->attempts() === 1) {
            Log::info('Resume quality below threshold, regenerating', [
                'resume_id' => $this->resumeVersion->id,
                'score' => $score,
                'issues' => $qualityResult['issues'] ?? [],
            ]);

            // Retry with quality feedback injected
            $this->release(5);

            return;
        }

        // Store the resume data
        $this->resumeVersion->update([
            'resume_data' => $resumeData,
            'quality_score' => $score,
            'status' => 'ready',
            'generation_context' => [
                'life_stage' => $lifeStage,
                'had_voice_profile' => $voiceData !== null,
                'had_career_profile' => ! empty($careerData['work_history']),
                'quality_details' => $qualityResult,
            ],
        ]);

        // Generate PDF using DomPDF (reuse existing ResultsPdf pattern)
        $this->generatePdf($resumeData, $resumeText);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Resume generation failed', [
            'resume_id' => $this->resumeVersion->id,
            'error' => $exception->getMessage(),
        ]);

        $this->resumeVersion->update(['status' => 'failed']);
    }

    private function detectLifeStage(array $careerData): string
    {
        $workCount = count($careerData['work_history'] ?? []);
        $hasEducation = ! empty($careerData['education']);

        if ($workCount === 0 && ! $hasEducation) {
            return 'middle_school';
        }

        if ($workCount === 0) {
            return 'high_school';
        }

        if ($workCount <= 2) {
            return $hasEducation ? 'college' : 'early_career';
        }

        return 'experienced';
    }

    private function renderResumeAsText(array $data): string
    {
        $lines = [];

        if (! empty($data['summary'])) {
            $lines[] = "PROFESSIONAL SUMMARY\n{$data['summary']}";
        }

        if (! empty($data['work'])) {
            $lines[] = "\nWORK EXPERIENCE";
            foreach ($data['work'] as $entry) {
                $lines[] = "{$entry['position']} at {$entry['company']} ({$entry['startDate']} - {$entry['endDate']})";
                foreach ($entry['highlights'] ?? [] as $h) {
                    $lines[] = "  - {$h}";
                }
            }
        }

        if (! empty($data['education'])) {
            $lines[] = "\nEDUCATION";
            foreach ($data['education'] as $entry) {
                $lines[] = "{$entry['studyType']} in {$entry['area']} — {$entry['institution']}";
            }
        }

        if (! empty($data['skills'])) {
            $lines[] = "\nSKILLS";
            foreach ($data['skills'] as $group) {
                $keywords = implode(', ', $group['keywords'] ?? []);
                $lines[] = "{$group['name']}: {$keywords}";
            }
        }

        return implode("\n", $lines);
    }

    private function generatePdf(array $resumeData, string $resumeText): void
    {
        try {
            $html = view('resumes.pdf', ['resume' => $resumeData])->render();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $content = $pdf->output();

            $path = "resumes/{$this->resumeVersion->user_id}/{$this->resumeVersion->id}.pdf";
            Storage::disk('s3')->put($path, $content, ['ContentType' => 'application/pdf']);

            $this->resumeVersion->update(['file_path_pdf' => $path]);
        } catch (\Throwable $e) {
            Log::warning('Resume PDF generation failed, resume data still saved', [
                'resume_id' => $this->resumeVersion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
