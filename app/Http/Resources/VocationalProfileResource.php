<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VocationalProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opening_synthesis' => $this->opening_synthesis,
            'vocational_orientation' => $this->vocational_orientation,
            'primary_pathways' => $this->primary_pathways,
            'specific_considerations' => $this->specific_considerations,
            'next_steps' => $this->next_steps,
            'primary_domain' => $this->primary_domain,
            'mode_of_work' => $this->mode_of_work,
            'secondary_orientation' => $this->secondary_orientation,
            'ministry_integration' => $this->ministry_integration,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
