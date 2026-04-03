<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MentorNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'mentor_id',
        'student_id',
        'content',
        'visibility',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isShared(): bool
    {
        return $this->visibility === 'shared';
    }

    public function scopeShared($query)
    {
        return $query->where('visibility', 'shared');
    }

    public function scopeForStudent($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
