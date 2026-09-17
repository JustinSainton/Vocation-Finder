<?php

namespace App\Models;

use App\Enums\CampusResourceKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An institution-specific link for a service every campus has.
 */
class CollegeResource extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['college_id', 'kind', 'name', 'url'];

    protected function casts(): array
    {
        return ['kind' => CampusResourceKind::class];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
