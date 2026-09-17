<?php

namespace App\Notifications;

use App\Models\ParentConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link a parent needs in order to say yes.
 *
 * Without this the age gate is a dead end: a student nominates a parent and
 * nothing reaches them. It is sent on demand rather than to a `User`, because
 * a parent does not have an account and should not need one.
 *
 * The copy states the privacy boundary up front rather than burying it. A
 * parent who expects to read the conversations and later cannot will feel
 * misled; a student who suspects their parent is reading will not be honest
 * with the coach, and the honesty is what the whole thing runs on. Saying it
 * plainly at the moment of consent is the only place that costs nothing.
 *
 * It deliberately carries nothing the student wrote — only their name. The
 * consent request is not a preview of the product.
 */
class ParentConsentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ParentConsent $consent,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->consent->user->name;

        return (new MailMessage)
            ->subject("{$student} is asking for your okay")
            ->greeting("Hello {$this->consent->parent_name},")
            ->line("{$student} has finished a vocational assessment and would like to keep going with a coach. That needs your permission first.")
            ->line('The coach reads what they wrote and helps them find one concrete thing to do next — one step, not a list of careers to choose between.')
            ->action('Give your permission', route('parent-consent.show', $this->consent->token))
            ->line("**What you will see:** what {$student} has finished, what they are working on now, and one thing worth asking them about.")
            ->line('**What you will not see:** their conversations with the coach, or what they write about themselves. That part stays theirs, and it is the reason they will tell it the truth.')
            ->line('You can withdraw your permission whenever you want. Nothing they have written is ever deleted.')
            ->salutation('— The Vocation Finder team');
    }
}
