<?php

namespace App\Mail;

use App\Models\VocationalProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
        return new Envelope(
            subject: 'Your Vocational Profile',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.results',
            with: [
                'profile' => $this->profile,
                'resultsUrl' => url("/api/v1/assessments/{$this->profile->assessment_id}/results"),
            ],
        );
    }
}
