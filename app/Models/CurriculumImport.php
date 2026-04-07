<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'source_type',
        'source_path',
        'status',
        'proposed_structure',
        'final_structure',
        'metadata',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'proposed_structure' => 'array',
            'final_structure' => 'array',
            'metadata' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
