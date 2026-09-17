<?php

namespace Database\Factories;

use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Syllabus>
 */
class SyllabusFactory extends Factory
{
    protected $model = Syllabus::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'enrollment_id' => null,
            'artifact_id' => null,
            'course_code' => 'ENG 101',
            'course_title' => 'Composition I',
            'term' => 'Fall',
            'source_text' => "ENG 101 Composition I\n\nReading response due Sept 12\nMidterm essay due Oct 14\nFinal portfolio due Dec 5",
            'parsed_at' => null,
            'discarded' => null,
        ];
    }
}
