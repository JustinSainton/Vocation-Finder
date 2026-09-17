<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When to invite this student back.
 *
 * One row per student, so two invitations can never be outstanding at once.
 */
class BrainstormSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'cadence_days',
        'last_invited_at',
        'last_attended_at',
        'consecutive_declines',
    ];

    protected $attributes = [
        'cadence_days' => 30,
        'consecutive_declines' => 0,
    ];

    protected function casts(): array
    {
        return [
            'last_invited_at' => 'datetime',
            'last_attended_at' => 'datetime',
            'cadence_days' => 'integer',
            'consecutive_declines' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
