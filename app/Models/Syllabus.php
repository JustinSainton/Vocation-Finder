<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded syllabus and everything that came out of it.
 */
class Syllabus extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'syllabi';

    protected $fillable = [
        'user_id', 'enrollment_id', 'artifact_id',
        'course_code', 'course_title', 'term',
        'source_text', 'parsed_at', 'discarded',
    ];

    protected function casts(): array
    {
        return [
            'parsed_at' => 'datetime',
            'discarded' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * The assignments, which are milestones — not a second kind of object.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }
}
