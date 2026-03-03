<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'status',
        'current_question_index',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(ConversationTurn::class)->orderBy('sort_order');
    }
}
