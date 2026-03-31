<?php

namespace App\Models;

use App\Support\ConversationLocale;
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

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    public function translationForLocale(?string $locale): ?QuestionTranslation
    {
        $normalizedLocale = ConversationLocale::normalize($locale);
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $normalizedLocale)
            ?? $translations->firstWhere('locale', ConversationLocale::DEFAULT);
    }

    public function localizedQuestionText(?string $locale): string
    {
        return $this->translationForLocale($locale)?->question_text ?? $this->question_text;
    }

    public function localizedConversationPrompt(?string $locale): ?string
    {
        return $this->translationForLocale($locale)?->conversation_prompt ?? $this->conversation_prompt;
    }

    /**
     * @return array<int, string>
     */
    public function localizedFollowUpPrompts(?string $locale): array
    {
        return $this->translationForLocale($locale)?->follow_up_prompts ?? ($this->follow_up_prompts ?? []);
    }
}
