<?php

namespace Database\Factories;

use App\Enums\ArtifactKind;
use App\Models\Artifact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => ArtifactKind::Essay,
            'title' => 'The essay about my grandfather\'s shop',
            'note' => null,
            'link_url' => 'https://docs.example.com/d/the-essay',
        ];
    }

    public function stored(string $path = 'artifacts/essay.pdf'): static
    {
        return $this->state(fn () => [
            'link_url' => null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'essay.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
        ]);
    }
}
