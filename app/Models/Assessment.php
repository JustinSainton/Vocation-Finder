<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'mode',
        'status',
        'locale',
        'speech_locale',
        'guest_token',
        'metadata',
        'started_at',
        'completed_at',
        'data_retention_until',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'data_retention_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function conversationSession(): HasOne
    {
        return $this->hasOne(ConversationSession::class);
    }

    public function vocationalProfile(): HasOne
    {
        return $this->hasOne(VocationalProfile::class);
    }

    public function curriculumPathway(): HasOne
    {
        return $this->hasOne(CurriculumPathway::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(AssessmentSurvey::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isGuest(): bool
    {
        return $this->user_id === null && $this->guest_token !== null;
    }
}
