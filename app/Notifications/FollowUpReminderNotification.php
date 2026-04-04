<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobApplication $application,
        public string $reminderType = 'follow_up',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->reminderType === 'next_action') {
            return (new MailMessage)
                ->subject("Reminder: {$this->application->next_action}")
                ->line("You have a scheduled action for your application to **{$this->application->company_name}**.")
                ->line("**{$this->application->job_title}**: {$this->application->next_action}")
                ->line('Open the app to update your application status.');
        }

        $days = $this->application->applied_at?->diffInDays(now()) ?? 7;

        return (new MailMessage)
            ->subject("Follow up on your application to {$this->application->company_name}?")
            ->line("You applied to **{$this->application->job_title}** at **{$this->application->company_name}** {$days} days ago.")
            ->line('Consider sending a follow-up to show your continued interest.')
            ->line('Open the app to update your application status or mark it as ghosted.');
    }
}
