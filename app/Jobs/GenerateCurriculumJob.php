<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\CurriculumPathway;
use App\Models\User;
use App\Services\CurriculumEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCurriculumJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public User $user,
        public Assessment $assessment,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(CurriculumEngine $engine): void
    {
        $engine->generate($this->user, $this->assessment);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Curriculum generation failed', [
            'user_id' => $this->user->id,
            'assessment_id' => $this->assessment->id,
            'error' => $exception->getMessage(),
        ]);

        CurriculumPathway::updateOrCreate(
            [
                'user_id' => $this->user->id,
                'assessment_id' => $this->assessment->id,
            ],
            [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ],
        );
    }
}
