<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalizedContent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'course_module_id',
        'assessment_id',
        'status',
        'content_blocks',
        'personalization_context',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'personalization_context' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isGenerating(): bool
    {
        return in_array($this->status, ['pending', 'generating']);
    }
}
