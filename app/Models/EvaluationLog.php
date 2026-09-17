<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\EvaluationOutcome;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One engine run, as it happened.
 *
 * Append-only, for the same reason {@see ReadinessSnapshot} is: a log you can
 * edit is a log you cannot trust, and the only reason to rewrite this row is
 * to make a bad week look better than it was.
 */
class EvaluationLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'assessment_id', 'user_id',
        'model_version', 'prompt_version', 'taxonomy_version',
        'outcome', 'failure_reason',
        'confidence_level', 'signals_kept', 'response_quality',
        'narrative_attempts', 'red_team_findings', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => EvaluationOutcome::class,
            'confidence_level' => ConfidenceLevel::class,
            'red_team_findings' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Evaluation logs are append-only. A log you can edit is a log you cannot trust.');
        });

        static::deleting(function () {
            throw new RuntimeException('Evaluation logs are append-only.');
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
