<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'question_text',
        'conversation_prompt',
        'follow_up_prompts',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_prompts' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
