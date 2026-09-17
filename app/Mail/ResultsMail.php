<?php

namespace App\Mail;

use App\Models\VocationalProfile;
use App\Services\ResultsPdf;
use App\Support\AssessmentAccess;
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
                'resultsUrl' => $this->resultsUrl(),
            ],
        );
    }

    /**
     * The link the student saves.
     *
     * It used to point at the JSON API endpoint, which authorizes by an
     * `X-Guest-Token` header that a click from an inbox cannot send — so the
     * "return to your results anytime" button could never have worked for the
     * guests it was written for, and returned raw JSON for everyone else.
     *
     * It now points at the results page, carrying the assessment's own secret
     * for a guest. This is the copy of the link most likely to still exist in
     * six months, so it has to be the durable one.
     */
    protected function resultsUrl(): string
    {
        $assessment = $this->profile->assessment;
        $path = "/assessment/{$this->profile->assessment_id}/results";

        if ($assessment && filled($assessment->guest_token)) {
            return url($path.'?'.http_build_query([
                AssessmentAccess::TOKEN_PARAM => $assessment->guest_token,
            ]));
        }

        return url($path);
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
