<?php

namespace Database\Factories;

use App\Models\CourseMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseMedia>
 */
class CourseMediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'filename' => fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'disk' => 'public',
            'path' => 'course-media/test/'.fake()->uuid().'.jpg',
            'type' => 'image',
            'metadata' => null,
        ];
    }

    public function pdf(): static
    {
        return $this->state([
            'filename' => fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'type' => 'pdf',
            'path' => 'course-media/test/'.fake()->uuid().'.pdf',
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'filename' => fake()->uuid().'.mp4',
            'original_filename' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
            'type' => 'video',
            'path' => 'course-media/test/'.fake()->uuid().'.mp4',
        ]);
    }
}
