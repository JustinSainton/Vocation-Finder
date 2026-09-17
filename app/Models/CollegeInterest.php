<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One college on one student's list.
 *
 * A pivot model rather than a plain one so that `attach()` fills the uuid
 * primary key itself. The alternative — every caller passing a generated id —
 * is a rule that holds until the first place somebody forgets it, and then
 * fails at the database rather than in review.
 */
class CollegeInterest extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'college_interests';

    protected $fillable = ['user_id', 'college_id', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
