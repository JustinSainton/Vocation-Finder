<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTranslation extends Model
{
    use HasUuids;

    protected $fillable = [
        'question_id',
        'locale',
        'question_text',
        'conversation_prompt',
        'follow_up_prompts',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_prompts' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
