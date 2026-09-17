<?php

namespace Database\Factories;

use App\Enums\CampusResourceKind;
use App\Models\College;
use App\Models\CollegeResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollegeResource>
 */
class CollegeResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'college_id' => College::factory(),
            'kind' => CampusResourceKind::Tutoring,
            'name' => 'Learning Commons',
            'url' => 'https://example.edu/tutoring',
        ];
    }
}
