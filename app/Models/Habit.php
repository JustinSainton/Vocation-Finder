<?php

namespace App\Models;

use App\Enums\HabitCadence;
use App\Enums\HabitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * A repeated thing the coach prescribed, aimed at a named gap.
 *
 * An action is the one step a student takes next; a habit is the thing they
 * do again. The roadmap's constraint is that it is never generic, which is
 * enforced structurally rather than in the prompt: a habit cannot exist
 * without the gap it serves.
 */
class Habit extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'gap_id',
        'action_id',
        'status',
        'cadence',
        'title',
        'why',
        'started_at',
        'settled_at',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => HabitStatus::class,
            'cadence' => HabitCadence::class,
            'started_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Habit $habit) {
            throw new RuntimeException(
                'Habits are retired, never deleted. Having tried something is information about the student that outlives the trying.'
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

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(HabitCheckIn::class);
    }

    /**
     * Setting a habit down is a decision, not a failure, so it takes the same
     * shape as skipping an action: recorded, reasoned, and never destructive.
     */
    public function retire(?string $reason = null): bool
    {
        return $this->forceFill([
            'status' => HabitStatus::Retired,
            'why' => $this->why,
            'settled_at' => now(),
        ])->save();
    }

    public function pause(): bool
    {
        return $this->forceFill(['status' => HabitStatus::Paused])->save();
    }

    public function resume(): bool
    {
        return $this->forceFill(['status' => HabitStatus::Active, 'settled_at' => null])->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', HabitStatus::Active->value);
    }
}
