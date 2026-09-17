<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'question_id',
        'response_text',
        'response_locale',
        'audio_transcript',
        'audio_storage_path',
        'ai_preliminary_analysis',
        'duration_seconds',
        'response_quality_score',
        'response_quality_bands',
    ];

    protected function casts(): array
    {
        return [
            'ai_preliminary_analysis' => 'array',
            'response_quality_bands' => 'array',
            'response_quality_score' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
