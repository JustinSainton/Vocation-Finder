<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSurvey extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'type',
        'clarity_score',
        'action_score',
    ];

    protected function casts(): array
    {
        return [
            'clarity_score' => 'integer',
            'action_score' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
