<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'content_blocks' => null,
            'estimated_duration' => fake()->randomElement(['1 hour', '2 hours', '30 minutes']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
            'difficulty_level' => fake()->randomElement(['foundational', 'intermediate', 'advanced']),
            'phase_tag' => fake()->randomElement(['discovery', 'deepening', 'integration', 'application']),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
