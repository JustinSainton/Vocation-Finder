<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'college_id' => null,
            'college_name' => fake()->company().' State University',
            'program' => fake()->words(2, true),
            'started_on' => now()->subMonth()->toDateString(),
            'expected_end_on' => null,
            'ended_at' => null,
        ];
    }

    /**
     * A student who has left or finished.
     */
    public function ended(): static
    {
        return $this->state(fn () => ['ended_at' => now()->subWeek()]);
    }
}
