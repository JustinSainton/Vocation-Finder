<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'style_analysis',
        'banned_phrases',
        'preferred_verbs',
        'avg_sentence_length',
        'tone_register',
        'sample_count',
        'writing_samples',
    ];

    protected function casts(): array
    {
        return [
            'style_analysis' => 'array',
            'banned_phrases' => 'array',
            'preferred_verbs' => 'array',
            'writing_samples' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
