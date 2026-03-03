<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function taggedCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_vocational_category')
            ->withPivot('relevance_weight')
            ->withTimestamps();
    }
}
