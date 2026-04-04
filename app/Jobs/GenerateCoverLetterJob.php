<?php

namespace App\Jobs;

use App\Ai\Agents\CoverLetterWriterAgent;
use App\Ai\Tools\CompanyResearchTool;
use App\Models\CoverLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

class GenerateCoverLetterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 2;

    public function __construct(
        public CoverLetter $coverLetter,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(): void
    {
        $this->coverLetter->update(['status' => 'generating']);

        $user = $this->coverLetter->user;
        $jobListing = $this->coverLetter->jobListing;
        $assessment = $this->coverLetter->assessment;

        $profile = $assessment?->vocationalProfile;
        $careerProfile = $user->careerProfile;
        $voiceProfile = $user->voiceProfile;

        $careerData = $careerProfile ? [
            'work_history' => $careerProfile->work_history ?? [],
            'education' => $careerProfile->education ?? [],
            'skills' => $careerProfile->skills ?? [],
        ] : [];

        $vocationalData = $profile ? [
            'primary_domain' => $profile->primary_domain,
            'primary_pathways' => $profile->primary_pathways,
            'vocational_orientation' => $profile->vocational_orientation,
            'ministry_integration' => $profile->ministry_integration,
            'opening_synthesis' => $profile->opening_synthesis,
        ] : [];

        $jobData = $jobListing ? [
            'title' => $jobListing->title,
            'company' => $jobListing->company_name,
            'company_url' => $jobListing->company_url,
            'description' => mb_substr($jobListing->description_plain ?? $jobListing->description ?? '', 0, 3000),
            'location' => $jobListing->location,
        ] : [];

        $voiceData = $voiceProfile ? [
            'tone_register' => $voiceProfile->tone_register,
            'avg_sentence_length' => $voiceProfile->avg_sentence_length,
            'preferred_verbs' => $voiceProfile->preferred_verbs,
            'banned_phrases' => $voiceProfile->banned_phrases,
        ] : null;

        // Fetch company research if not already present
        $companyResearch = $this->coverLetter->company_research;
        if (! $companyResearch && ! empty($jobData['company'])) {
            try {
                $tool = new CompanyResearchTool;
                $result = json_decode($tool->handle(new Request(['company_name' => $jobData['company']])), true);
                if (is_array($result) && ! empty($result['description'])) {
                    $companyResearch = $result;
                    $this->coverLetter->update(['company_research' => $companyResearch]);
                }
            } catch (\Throwable $e) {
                Log::info('Company research failed, proceeding without', ['error' => $e->getMessage()]);
            }
        }

        $agent = new CoverLetterWriterAgent(
            careerProfile: $careerData,
            vocationalProfile: $vocationalData,
            jobDescription: $jobData,
            companyResearch: $companyResearch,
            voiceProfile: $voiceData,
        );

        $response = $agent->prompt($agent->buildPrompt());
        $content = $response->text();

        // Quality scoring — same pipeline as resumes
        $qualityScore = null;
        try {
            $qualityAgent = new \App\Ai\Agents\ResumeQualityAgent(
                resumeText: $content,
                jobTitle: $jobData['title'] ?? 'General',
            );
            $qualityResult = $qualityAgent->prompt($qualityAgent->buildPrompt())->json();
            $qualityScore = $qualityResult['total_score'] ?? null;
        } catch (\Throwable $e) {
            Log::info('Cover letter quality scoring skipped', ['error' => $e->getMessage()]);
        }

        $this->coverLetter->update([
            'content' => $content,
            'quality_score' => $qualityScore,
            'status' => 'ready',
            'generation_context' => [
                'had_voice_profile' => $voiceData !== null,
                'had_company_research' => $companyResearch !== null,
                'had_career_profile' => ! empty($careerData['work_history']),
            ],
        ]);

        // Generate PDF and track usage
        $this->generatePdf($content);
        $this->trackUsage();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Cover letter generation failed', [
            'cover_letter_id' => $this->coverLetter->id,
            'error' => $exception->getMessage(),
        ]);

        $this->coverLetter->update(['status' => 'failed']);
    }

    private function trackUsage(): void
    {
        try {
            $user = $this->coverLetter->user;
            if (method_exists($user, 'reportMeterEvent')) {
                $user->reportMeterEvent('cover_letter_generations', quantity: 1);
            }
        } catch (\Throwable $e) {
            Log::info('Cover letter usage metering skipped', ['error' => $e->getMessage()]);
        }
    }

    private function generatePdf(string $content): void
    {
        try {
            $html = view('cover-letters.pdf', ['content' => $content])->render();
            $pdf = Pdf::loadHTML($html);
            $output = $pdf->output();

            $path = "cover-letters/{$this->coverLetter->user_id}/{$this->coverLetter->id}.pdf";
            Storage::disk('s3')->put($path, $output, ['ContentType' => 'application/pdf']);

            $this->coverLetter->update(['file_path_pdf' => $path]);
        } catch (\Throwable $e) {
            Log::warning('Cover letter PDF generation failed, content still saved', [
                'cover_letter_id' => $this->coverLetter->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
