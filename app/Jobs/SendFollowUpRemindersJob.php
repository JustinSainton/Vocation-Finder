<?php

namespace App\Jobs;

use App\Models\JobApplication;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFollowUpRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $count = 0;

        // 7-day follow-up: applications in 'applied' status with no events for 7 days
        $followUps = JobApplication::where('status', 'applied')
            ->whereBetween('applied_at', [now()->subDays(8), now()->subDays(7)])
            ->with('user')
            ->get();

        foreach ($followUps as $application) {
            $application->user->notify(new FollowUpReminderNotification($application, 'follow_up'));
            $count++;
        }

        // Next action reminders: applications with next_action_date = today
        $actionReminders = JobApplication::whereDate('next_action_date', today())
            ->whereNotNull('next_action')
            ->whereNotIn('status', JobApplication::TERMINAL_STATUSES)
            ->with('user')
            ->get();

        foreach ($actionReminders as $application) {
            $application->user->notify(new FollowUpReminderNotification($application, 'next_action'));
            $count++;
        }

        if ($count > 0) {
            Log::info("Sent {$count} follow-up reminders.");
        }
    }
}
