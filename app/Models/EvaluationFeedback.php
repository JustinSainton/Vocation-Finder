<?php

namespace App\Models;

use App\Enums\FeedbackQuestion;
use App\Enums\FeedbackStanding;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * What a student said about their own result.
 *
 * One answer per question per assessment, and the first one stands. A student
 * re-reading their portrait a fortnight later may feel differently, but that
 * is a new reading of a new moment rather than a correction of what they
 * thought at the time — and overwriting the first answer would quietly delete
 * the only record of a first impression, which is the impression that decides
 * whether they come back.
 */
class EvaluationFeedback extends Model
{
    use HasUuids;

    protected $table = 'evaluation_feedback';

    public const UPDATED_AT = null;

    protected $fillable = [
        'assessment_id', 'user_id', 'prompt_version',
        'question', 'standing', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'question' => FeedbackQuestion::class,
            'standing' => FeedbackStanding::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Feedback records what they said at the time and is never rewritten.');
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
