<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student who is actually in college now.
 *
 * The whole in-college layer turns on this record existing, and it is entered
 * by the student rather than inferred. We could guess from a grade level and a
 * date, and a wrong guess would either withhold the layer from somebody who
 * has started or push syllabus prompts at a high school senior in May.
 */
class Enrollment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id', 'college_id', 'college_name', 'program',
        'started_on', 'expected_end_on', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'expected_end_on' => 'date',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function syllabi(): HasMany
    {
        return $this->hasMany(Syllabus::class);
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }
}
