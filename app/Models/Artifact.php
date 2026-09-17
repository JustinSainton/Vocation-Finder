<?php

namespace App\Models;

use App\Enums\ArtifactKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Something the student made, kept where they can find it again.
 *
 * The locker stores; it does not produce. There is deliberately no agent, no
 * generator and no coach tool that can write a row here — the product's line
 * is that the tool never does the student's work for them, and a locker the
 * coach could fill is a locker full of the coach's work with the student's
 * name on it.
 */
class Artifact extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'kind',
        'title',
        'note',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'link_url',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ArtifactKind::class,
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Exactly one of file or link. No column can say "one of these two",
         * so the rule lives here — and it throws rather than silently
         * correcting, because an artifact that is neither is an empty row the
         * student will click on and a row that is both is two artifacts
         * wearing one title.
         */
        static::saving(function (Artifact $artifact) {
            $hasFile = filled($artifact->path);
            $hasLink = filled($artifact->link_url);

            if ($hasFile === $hasLink) {
                throw new RuntimeException(
                    'An artifact is either a stored file or a link to one, and exactly one of the two.',
                );
            }

            if ($hasFile && blank($artifact->disk)) {
                throw new RuntimeException('A stored artifact must record the disk it was written to.');
            }
        });

        /*
         * The file goes when the row goes. A locker that leaks orphaned files
         * for every draft a teenager thought better of is a quiet archive of
         * things they chose to remove.
         */
        static::deleted(function (Artifact $artifact) {
            if (filled($artifact->path) && filled($artifact->disk)) {
                Storage::disk($artifact->disk)->delete($artifact->path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFile(): bool
    {
        return filled($this->path);
    }
}
