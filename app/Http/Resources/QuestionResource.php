<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'conversation_prompt' => $this->conversation_prompt,
            'follow_up_prompts' => $this->follow_up_prompts ?? [],
            'sort_order' => $this->sort_order,
            'category_name' => $this->whenLoaded('category', fn () => $this->category->name),
            'category_slug' => $this->whenLoaded('category', fn () => $this->category->slug),
        ];
    }
}
