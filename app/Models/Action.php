<?php

namespace App\Models;

use App\Enums\ActionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * The one thing a student is doing next.
 *
 * The vision's success test is whether a student finishes able to describe, in
 * their own words, one thing they are going to do. A list of twenty is the
 * decision friction this product exists to remove, not a richer version of
 * help, so "one at a time" is a database constraint here rather than a
 * guideline.
 */
class Action extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'gap_id',
        'status',
        'active_marker',
        'title',
        'rationale',
        'reflection',
        'assigned_at',
        'settled_at',
    ];

    /**
     * A new action is active. The column carries the same default, but
     * Eloquent does not read database defaults back into a freshly created
     * model, so the status would be null until the row was reloaded — and the
     * saving hook below needs it now, to set the uniqueness marker.
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActionStatus::class,
            'assigned_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Keep `active_marker` in step with the status automatically, so the
     * uniqueness guarantee cannot be defeated by writing a status directly.
     */
    protected static function booted(): void
    {
        static::saving(function (Action $action) {
            $action->active_marker = $action->status === ActionStatus::Active ? 1 : null;
        });

        static::deleting(function (Action $action) {
            throw new RuntimeException(
                'Actions are completed or skipped, never deleted. A step a student chose not to take is information.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gap(): BelongsTo
    {
        return $this->belongsTo(Gap::class);
    }

    /**
     * Completing an action is the only thing that closes the gap it aimed at.
     */
    public function complete(?string $reflection = null): bool
    {
        $this->forceFill([
            'status' => ActionStatus::Completed,
            'reflection' => $reflection ?: $this->reflection,
            'settled_at' => now(),
        ])->save();

        return true;
    }

    /**
     * A skipped action leaves its gap open. Not doing something is a fact
     * about the step, never a verdict on the student.
     */
    public function skip(?string $reason = null): bool
    {
        return $this->forceFill([
            'status' => ActionStatus::Skipped,
            'reflection' => $reason ?: $this->reflection,
            'settled_at' => now(),
        ])->save();
    }

    public function isActive(): bool
    {
        return ! $this->status->isSettled();
    }

    public function scopeActive($query)
    {
        return $query->where('status', ActionStatus::Active->value);
    }
}
