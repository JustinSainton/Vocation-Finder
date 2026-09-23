<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CrisisResourceData extends Data
{
    public function __construct(
        public string $name,
        public string $contact,
        public string $note,
    ) {}
}
