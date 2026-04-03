<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CareerProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'work_history',
        'education',
        'skills',
        'certifications',
        'volunteer',
        'raw_import_data',
        'import_source',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'work_history' => 'array',
            'education' => 'array',
            'skills' => 'array',
            'certifications' => 'array',
            'volunteer' => 'array',
            'raw_import_data' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
