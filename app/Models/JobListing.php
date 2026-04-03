<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobListing extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'company_name',
        'company_url',
        'location',
        'is_remote',
        'salary_min',
        'salary_max',
        'salary_currency',
        'description',
        'description_plain',
        'required_skills',
        'source',
        'source_id',
        'source_url',
        'soc_code',
        'raw_data',
        'posted_at',
        'expires_at',
        'last_seen_at',
        'classification_status',
    ];

    protected function casts(): array
    {
        return [
            'is_remote' => 'boolean',
            'required_skills' => 'array',
            'raw_data' => 'array',
            'posted_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function vocationalCategories(): BelongsToMany
    {
        return $this->belongsToMany(VocationalCategory::class, 'job_listing_categories')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_jobs')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeClassified(Builder $query): Builder
    {
        return $query->where('classification_status', 'classified');
    }

    public function scopePendingClassification(Builder $query): Builder
    {
        return $query->where('classification_status', 'pending');
    }
}
