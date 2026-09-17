<?php

namespace App\Models;

use App\Enums\ReadinessLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One reading of a student's readiness, kept.
 *
 * Append-only. A snapshot records what the student was told at a moment in
 * time; editing it afterwards would change their own history out from under
 * them, and the history is what makes readiness a thing you work at rather
 * than a thing you are.
 */
class ReadinessSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'level',
        'factors',
        'reason',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => ReadinessLevel::class,
            'factors' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ReadinessSnapshot $snapshot) {
            throw new RuntimeException(
                'Readiness snapshots are append-only. Record a new one instead of editing history.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
