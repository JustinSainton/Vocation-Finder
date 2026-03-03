<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VocationalProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource->assessment?->user;
        $isPaid = $user && $user->subscribed();
        $allowedSections = $isPaid
            ? null
            : config('vocation.free_tier.output_sections');

        return [
            'id' => $this->id,
            'opening_synthesis' => $this->opening_synthesis,
            'vocational_orientation' => $this->vocational_orientation,
            'primary_pathways' => $this->when(
                $allowedSections === null || in_array('primary_pathways', $allowedSections),
                $this->primary_pathways,
            ),
            'specific_considerations' => $this->when(
                $allowedSections === null || in_array('specific_considerations', $allowedSections),
                $this->specific_considerations,
            ),
            'next_steps' => $this->when(
                $allowedSections === null || in_array('next_steps', $allowedSections),
                $this->next_steps,
            ),
            'primary_domain' => $this->primary_domain,
            'mode_of_work' => $this->mode_of_work,
            'secondary_orientation' => $this->secondary_orientation,
            'ministry_integration' => $this->when(
                $allowedSections === null || in_array('ministry_integration', $allowedSections),
                $this->ministry_integration,
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
