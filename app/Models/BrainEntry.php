<?php

namespace App\Models;

use App\Enums\BrainEntrySource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One thing a student said about their own work, kept verbatim.
 *
 * Never deleted and never rewritten. Both are enforced here: the brain is a
 * minor's private record of who they are becoming, and the product's promise
 * is that it hands those words back unchanged.
 */
class BrainEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'action_id',
        'assessment_id',
        'source',
        'content',
        'context',
        'audio_storage_path',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => BrainEntrySource::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (BrainEntry $entry) {
            throw new RuntimeException(
                'Brain entries are never deleted. A lapsed subscription freezes the brain; it does not destroy it.'
            );
        });

        // Editing what someone said is not a feature. An entry can gain
        // context or an audio path, but the words themselves are fixed at
        // capture — otherwise "their own words back" is only a claim.
        static::updating(function (BrainEntry $entry) {
            if ($entry->isDirty('content')) {
                throw new RuntimeException(
                    'A brain entry\'s content is what the student said and cannot be rewritten.'
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
