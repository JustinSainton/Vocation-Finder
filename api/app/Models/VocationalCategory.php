<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VocationalCategory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'ministry_connection',
        'career_pathways',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'career_pathways' => 'array',
        ];
    }
}
