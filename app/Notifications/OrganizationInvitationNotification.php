<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orgName = $this->invitation->organization->name;
        $acceptUrl = url("/invitations/{$this->invitation->token}/accept");

        return (new MailMessage)
            ->subject("You've been invited to join {$orgName}")
            ->greeting("Hello!")
            ->line("You have been invited to join **{$orgName}** as a {$this->invitation->role}.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("This invitation expires on {$this->invitation->expires_at->format('F j, Y')}.");
    }
}
