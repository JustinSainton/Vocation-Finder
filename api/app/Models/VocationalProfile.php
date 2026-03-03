<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocationalProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'opening_synthesis',
        'vocational_orientation',
        'primary_pathways',
        'specific_considerations',
        'next_steps',
        'ai_analysis_raw',
        'primary_domain',
        'mode_of_work',
        'secondary_orientation',
        'category_scores',
        'ministry_integration',
    ];

    protected function casts(): array
    {
        return [
            'primary_pathways' => 'array',
            'next_steps' => 'array',
            'ai_analysis_raw' => 'array',
            'category_scores' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
