<?php

namespace App\Services;

use App\Models\VocationalProfile;
use App\Support\VocationalProfileCopy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ResultsPdf
{
    public function render(VocationalProfile $profile): string
    {
        $copy = VocationalProfileCopy::forLocale($profile->assessment->locale ?? null);

        return Pdf::loadView('pdf.results', [
            'profile' => $profile,
            'copy' => $copy,
            'resultsUrl' => url("/api/v1/assessments/{$profile->assessment_id}/results"),
        ])
            ->setPaper('letter')
            ->output();
    }

    public function filename(VocationalProfile $profile): string
    {
        $domain = Str::slug((string) ($profile->primary_domain ?: 'vocational-profile'));

        return "{$domain}-{$profile->assessment_id}.pdf";
    }
}
