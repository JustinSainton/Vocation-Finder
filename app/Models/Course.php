<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'content_blocks',
        'vocational_category_id',
        'estimated_duration',
        'sort_order',
        'is_published',
        'published_at',
        'requires_personalization',
        'difficulty_level',
        'phase_tag',
        'prerequisite_course_ids',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'is_published' => 'boolean',
            'requires_personalization' => 'boolean',
            'published_at' => 'datetime',
            'prerequisite_course_ids' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function vocationalCategory(): BelongsTo
    {
        return $this->belongsTo(VocationalCategory::class);
    }

    public function vocationalCategories(): BelongsToMany
    {
        return $this->belongsToMany(VocationalCategory::class, 'course_vocational_category')
            ->withPivot('relevance_weight')
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(CourseMedia::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
