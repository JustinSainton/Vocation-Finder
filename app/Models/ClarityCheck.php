<?php

namespace App\Models;

use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One reading of "how clear are you about what to do next".
 *
 * Append-only, like {@see EvaluationLog} and {@see EvaluationFeedback}: the
 * whole value of a before-reading is that it was taken before, and a row that
 * can be rewritten afterwards is a row that proves nothing.
 */
class ClarityCheck extends Model
{
    use HasFactory;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['assessment_id', 'user_id', 'moment', 'standing'];

    protected function casts(): array
    {
        return [
            'moment' => ClarityMoment::class,
            'standing' => ClarityStanding::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('A clarity reading records what somebody said at that moment and is never rewritten.');
        });

        static::deleting(function () {
            throw new RuntimeException('Clarity readings are append-only.');
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
