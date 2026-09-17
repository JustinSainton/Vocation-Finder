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

        /*
         * This pointed at `/invitations/{token}/accept`, which was an API
         * route that no web request ever reached — the link in every
         * invitation email was dead. The entry path is a page now, and a page
         * is the right thing anyway: somebody with no account needs to be told
         * what they are joining before they are asked to join it.
         */
        $acceptUrl = url("/invitations/{$this->invitation->token}");

        return (new MailMessage)
            ->subject("{$orgName} has set up a place for you")
            ->greeting('Hello,')
            ->line("{$orgName} is using Vocation Finder, and has set up a place for you.")
            ->line('It starts with twenty questions about what you are actually like, and ends with one concrete thing to do next.')
            ->action('See what this is', $acceptUrl)
            ->line("The link works until {$this->invitation->expires_at->format('F j, Y')}.");
    }
}
