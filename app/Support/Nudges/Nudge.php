<?php

namespace App\Support\Nudges;

/**
 * One invitation to come back, ready to be carried by whatever channel we
 * eventually choose.
 *
 * It is a value object rather than a string because the thing that makes an
 * invitation acceptable is that it has something *in* it. 2.4's rule is that
 * an invitation with nothing in it is a nag, so the body always arrives with
 * the student's own material behind it and the channel is never asked to
 * invent a reason.
 */
readonly class Nudge
{
    public function __construct(
        public string $body,
        public string $url,
        public ?string $reason = null,
    ) {}
}
