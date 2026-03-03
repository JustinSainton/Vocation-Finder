<?php

namespace App\Jobs;

use App\Mail\ResultsMail;
use App\Models\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Assessment $assessment,
        public string $email,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $profile = $this->assessment->vocationalProfile;

        if (! $profile) {
            Log::warning('EmailResultsJob: No vocational profile found for assessment', [
                'assessment_id' => $this->assessment->id,
            ]);

            return;
        }

        Mail::to($this->email)->send(new ResultsMail($profile));

        Log::info('Vocational profile emailed', [
            'assessment_id' => $this->assessment->id,
            'email' => $this->email,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to email vocational profile', [
            'assessment_id' => $this->assessment->id,
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
