<?php

namespace App\Models;

use App\Enums\GapStatus;
use App\Enums\GapType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One of the six things standing between a student and their next step.
 *
 * Gaps are closed, never deleted. The brain persistence policy makes that a
 * schema-level invariant rather than a preference: these are a minor's own
 * reflections on what is in their way, and a record of having closed something
 * is the most encouraging thing the product can show them two years later.
 */
class Gap extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'assessment_id',
        'signal_extraction_id',
        'type',
        'status',
        'summary',
        'evidence',
        'source',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GapType::class,
            'status' => GapStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Closing and deleting are not the same act, and only one of them is
     * allowed. Enforced on the model so no controller, job or tool can take
     * the shortcut by accident.
     */
    protected static function booted(): void
    {
        static::deleting(function (Gap $gap) {
            throw new RuntimeException(
                'Gaps are closed, never deleted. Call close() instead — see the brain persistence policy.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * The verified signal this gap was drawn from, when it came from one.
     */
    public function signalExtraction(): BelongsTo
    {
        return $this->belongsTo(SignalExtraction::class);
    }

    public function close(): bool
    {
        return $this->forceFill([
            'status' => GapStatus::Closed,
            'closed_at' => now(),
        ])->save();
    }

    public function markTesting(): bool
    {
        return $this->forceFill(['status' => GapStatus::Testing])->save();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [GapStatus::Open->value, GapStatus::Testing->value]);
    }

    public function scopeOfType($query, GapType ...$types)
    {
        return $query->whereIn('type', array_column($types, 'value'));
    }
}
