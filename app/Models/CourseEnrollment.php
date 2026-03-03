<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'course_slug',
        'status',
        'progress',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
