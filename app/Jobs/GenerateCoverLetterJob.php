<?php

namespace App\Jobs;

use App\Ai\Agents\CoverLetterWriterAgent;
use App\Models\CoverLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        $companyResearch = $this->coverLetter->company_research;

        $agent = new CoverLetterWriterAgent(
            careerProfile: $careerData,
            vocationalProfile: $vocationalData,
            jobDescription: $jobData,
            companyResearch: $companyResearch,
            voiceProfile: $voiceData,
        );

        $response = $agent->prompt($agent->buildPrompt());
        $content = $response->text();

        $this->coverLetter->update([
            'content' => $content,
            'status' => 'ready',
            'generation_context' => [
                'had_voice_profile' => $voiceData !== null,
                'had_company_research' => $companyResearch !== null,
                'had_career_profile' => ! empty($careerData['work_history']),
            ],
        ]);

        // Generate PDF
        $this->generatePdf($content);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Cover letter generation failed', [
            'cover_letter_id' => $this->coverLetter->id,
            'error' => $exception->getMessage(),
        ]);

        $this->coverLetter->update(['status' => 'failed']);
    }

    private function generatePdf(string $content): void
    {
        try {
            $html = view('cover-letters.pdf', ['content' => $content])->render();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
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
