<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Failed')
            ->greeting("Hello {$notifiable->name},")
            ->line('We were unable to process your latest payment.')
            ->line('Please update your payment method to continue using your subscription.')
            ->action('Update Payment Method', url('/billing/portal'));
    }
}
