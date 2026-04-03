<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResumeVersion extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'job_listing_id',
        'assessment_id',
        'resume_data',
        'file_path_pdf',
        'file_path_docx',
        'generation_context',
        'quality_score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'resume_data' => 'array',
            'generation_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
