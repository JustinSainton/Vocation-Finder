<?php

namespace App\Http\Resources;

use App\Support\ConversationLocale;
use App\Support\DemoMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = ConversationLocale::normalize($request->query('locale', $request->header('X-Locale')));

        return [
            'id' => $this->id,
            'locale' => $locale,
            'question_text' => $this->localizedQuestionText($locale),
            'conversation_prompt' => $this->localizedConversationPrompt($locale),
            'follow_up_prompts' => $this->localizedFollowUpPrompts($locale),
            'sort_order' => $this->sort_order,
            'category_name' => $this->whenLoaded('category', fn () => $this->category->name),
            'category_slug' => $this->whenLoaded('category', fn () => $this->category->slug),
            // The questions route is public, so the token has to be read
            // explicitly: without the sanctum guard every caller is a guest.
            'demo_answer' => $this->when(
                DemoMode::isActiveFor($request->user('sanctum')),
                fn () => DemoMode::answerFor($request->user('sanctum'), $this->resource),
            ),
        ];
    }
}
