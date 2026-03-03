<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseModule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'content_blocks',
        'personalization_prompts',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'personalization_prompts' => 'array',
        ];
    }

    public function personalizedContentFor(User $user, Assessment $assessment): ?PersonalizedContent
    {
        return $this->hasMany(PersonalizedContent::class)
            ->where('user_id', $user->id)
            ->where('assessment_id', $assessment->id)
            ->first();
    }

    public function personalizedContents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PersonalizedContent::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
