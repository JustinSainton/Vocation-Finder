<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurriculumPathway extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'assessment_id',
        'status',
        'phases',
        'ai_rationale',
        'pathway_summary',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'phases' => 'array',
            'ai_rationale' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function pathwayCourses(): HasMany
    {
        return $this->hasMany(CurriculumPathwayCourse::class)
            ->orderBy('phase')
            ->orderBy('position_in_phase');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isGenerating(): bool
    {
        return $this->status === 'generating';
    }

    public function coursesInPhase(string $phase): HasMany
    {
        return $this->pathwayCourses()->where('phase', $phase);
    }
}
