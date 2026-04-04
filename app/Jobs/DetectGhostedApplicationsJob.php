<?php

namespace App\Jobs;

use App\Models\ApplicationEvent;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectGhostedApplicationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $threshold = now()->subDays(14);

        $staleApplications = JobApplication::where('status', 'applied')
            ->where('applied_at', '<', $threshold)
            ->whereDoesntHave('events', function ($q) use ($threshold) {
                $q->where('occurred_at', '>', $threshold);
            })
            ->get();

        $count = 0;

        foreach ($staleApplications as $application) {
            $application->update(['status' => 'ghosted']);

            ApplicationEvent::create([
                'job_application_id' => $application->id,
                'event_type' => 'auto_ghosted',
                'from_status' => 'applied',
                'to_status' => 'ghosted',
                'details' => ['reason' => 'No activity for 14+ days'],
                'occurred_at' => now(),
            ]);

            $count++;
        }

        if ($count > 0) {
            Log::info("Auto-ghosted {$count} stale applications.");
        }
    }
}
