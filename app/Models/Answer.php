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
        'audio_transcript',
        'audio_storage_path',
        'ai_preliminary_analysis',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'ai_preliminary_analysis' => 'array',
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
