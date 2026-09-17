<?php

namespace App\Models;

use App\Enums\SignalTrack;
use App\Enums\SignalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single typed vocational signal, tied to the words it came from.
 */
class SignalExtraction extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'answer_id',
        'type',
        'track',
        'content',
        'verbatim',
        'constraint_nature',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => SignalType::class,
            'track' => SignalTrack::class,
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, SignalType ...$types): void
    {
        $query->whereIn('type', array_column($types, 'value'));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDemonstrated(Builder $query): void
    {
        $query->where('track', SignalTrack::Demonstrated->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAspirational(Builder $query): void
    {
        $query->where('track', SignalTrack::Aspiration->value);
    }
}
