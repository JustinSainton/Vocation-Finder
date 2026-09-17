<?php

namespace App\Mail;

use App\Models\ParentConsent;
use App\Support\ParentVisibility;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The monthly note home.
 *
 * Its whole payload is {@see ParentVisibility::summaryFor()} — the same single
 * function the family page uses. Assembling anything here would create a
 * second place that decides what a parent may see, and the second place is the
 * one that leaks.
 *
 * The email ends in one specific thing the parent can do. The vision's
 * mechanism for involving a family is giving them the question to ask, not a
 * window into the coaching: a parent handed a transcript reads, a parent
 * handed a question talks to their kid.
 */
class FamilyUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ParentConsent $consent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How '.$this->consent->user->name.' is doing',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.family-update',
            with: [
                'parentName' => $this->consent->parent_name,
                'summary' => ParentVisibility::summaryFor($this->consent->user),
                'url' => url('/family/'.$this->consent->token),
            ],
        );
    }
}
