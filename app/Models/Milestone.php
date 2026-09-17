<?php

namespace App\Models;

use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated outcome inside the plan.
 *
 * A milestone is **not an action**. An action is the single next step, and
 * there is only ever one of them ({@see Action}). A milestone is
 * the thing the step is for, and there are many. Conflating them would turn
 * the plan into a list of twenty things to do, which is the decision friction
 * the product exists to remove rather than a richer version of help.
 */
class Milestone extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'gap_id',
        'syllabus_id',
        'kind',
        'status',
        'title',
        'why',
        'due_on',
        'settled_at',
    ];

    protected $attributes = [
        'status' => 'not_yet',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MilestoneKind::class,
            'status' => MilestoneStatus::class,
            'due_on' => 'immutable_date',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Settling stamps itself, so "done" and "when it was done" cannot drift
     * apart, and unsettling clears the stamp rather than leaving a stale one.
     */
    protected static function booted(): void
    {
        static::saving(function (Milestone $milestone) {
            if ($milestone->status->isSettled()) {
                $milestone->settled_at ??= now();

                return;
            }

            $milestone->settled_at = null;
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
}
