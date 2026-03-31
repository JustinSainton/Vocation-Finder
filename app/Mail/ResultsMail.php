<?php

namespace App\Mail;

use App\Models\VocationalProfile;
use App\Services\ResultsPdf;
use App\Support\VocationalProfileCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResultsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VocationalProfile $profile,
    ) {}

    public function envelope(): Envelope
    {
        $copy = VocationalProfileCopy::forLocale($this->profile->assessment->locale ?? null);

        return new Envelope(
            subject: $copy['email_subject'],
        );
    }

    public function content(): Content
    {
        $copy = VocationalProfileCopy::forLocale($this->profile->assessment->locale ?? null);

        return new Content(
            view: 'mail.results',
            with: [
                'profile' => $this->profile,
                'copy' => $copy,
                'resultsUrl' => url("/api/v1/assessments/{$this->profile->assessment_id}/results"),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(ResultsPdf::class)->render($this->profile),
                app(ResultsPdf::class)->filename($this->profile),
            )->withMime('application/pdf'),
        ];
    }
}
