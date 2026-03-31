<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationTurn extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_session_id',
        'role',
        'content',
        'content_locale',
        'content_confidence',
        'stt_engine',
        'metadata',
        'audio_storage_path',
        'duration_seconds',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'content_confidence' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class, 'conversation_session_id');
    }
}
