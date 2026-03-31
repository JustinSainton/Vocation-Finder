<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'status' => $this->status,
            'locale' => $this->locale,
            'speech_locale' => $this->speech_locale,
            'guest_token' => $this->when($this->isGuest(), $this->guest_token),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'answer_count' => $this->whenLoaded('answers', fn () => $this->answers->count()),
            'vocational_profile' => new VocationalProfileResource($this->whenLoaded('vocationalProfile')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
