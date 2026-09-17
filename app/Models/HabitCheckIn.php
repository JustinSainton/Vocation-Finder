<?php

namespace App\Models;

use App\Support\HabitTracker;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One answer about one occasion.
 *
 * Append-only in the same sense the evaluation log is: an answer can gain a
 * note it did not have, but the answer itself cannot be rewritten. A history
 * that can be edited into a better-looking one is not a history, and the
 * coach reads this to decide whether a habit is the wrong size.
 */
class HabitCheckIn extends Model
{
    use HasUuids;

    protected $fillable = [
        'habit_id',
        'observed_on',
        'happened',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'happened' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (HabitCheckIn $checkIn) {
            if ($checkIn->isDirty(['habit_id', 'observed_on', 'happened'])) {
                throw new RuntimeException(
                    'A check-in records what happened on a day. Add a note to it, but do not rewrite it.'
                );
            }
        });

        static::deleting(function (HabitCheckIn $checkIn) {
            throw new RuntimeException('Check-ins are not deleted.');
        });
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    /**
     * A missed occasion the student explained. It is counted differently from
     * silence — see {@see HabitTracker::standing()}.
     */
    public function isExcused(): bool
    {
        return ! $this->happened && filled($this->note);
    }
}
