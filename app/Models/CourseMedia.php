<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CourseMedia extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'course_media';

    protected $fillable = [
        'course_id',
        'filename',
        'original_filename',
        'mime_type',
        'size_bytes',
        'disk',
        'path',
        'type',
        'metadata',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrl(int $expirationMinutes = 60): string
    {
        if ($this->disk === 's3') {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->path,
                now()->addMinutes($expirationMinutes),
            );
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function deleteFile(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }
}
