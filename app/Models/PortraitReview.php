<?php

namespace App\Models;

use App\Enums\ReviewDimension;
use App\Enums\ReviewStanding;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * What a human said about one portrait, on one dimension.
 *
 * Append-only like every other record of a judgement made at a moment. A
 * reviewer who changes their mind is welcome to; that is a second reading of a
 * different day, and the first one still happened.
 */
class PortraitReview extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'vocational_profile_id', 'reviewer_id', 'dimension', 'standing', 'note', 'prompt_version',
    ];

    protected function casts(): array
    {
        return [
            'dimension' => ReviewDimension::class,
            'standing' => ReviewStanding::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('A review records a judgement made at a moment and is never rewritten.');
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(VocationalProfile::class, 'vocational_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
