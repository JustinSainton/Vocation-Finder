<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pattern the system has already pointed out to this student.
 *
 * Kept so it is not pointed out again. No `label` or `summary` column, for the
 * same reason `brain_entries` has none: if there were somewhere for the
 * system's own wording of the pattern to sit, it would end up there, and what
 * gets handed back would stop being the student's.
 */
class SurfacedPattern extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'term',
        'entry_count',
        'first_said_on',
        'last_said_on',
        'surfaced_at',
    ];

    protected function casts(): array
    {
        return [
            'first_said_on' => 'date',
            'last_said_on' => 'date',
            'surfaced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
