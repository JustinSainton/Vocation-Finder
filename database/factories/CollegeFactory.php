<?php

namespace Database\Factories;

use App\Enums\CollegeControl;
use App\Enums\CollegeKind;
use App\Enums\IncomeBand;
use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<College>
 */
class CollegeFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->company().' University';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'city' => $this->faker->city(),
            'state' => $this->faker->randomElement(['CA', 'TX', 'NY', 'OH', 'FL']),
            'control' => CollegeControl::PrivateNonprofit,
            'kind' => CollegeKind::University,
            'acceptance_rate' => 0.45,
            'gpa_25th' => 3.20,
            'gpa_75th' => 3.80,
            'test_optional' => true,
            'tuition_in_state' => 38000,
            'tuition_out_of_state' => 38000,
            'room_and_board' => 12000,
            'net_price_by_income' => [
                IncomeBand::UpTo30k->value => 14000,
                IncomeBand::From30kTo48k->value => 17000,
                IncomeBand::From48kTo75k->value => 22000,
                IncomeBand::From75kTo110k->value => 30000,
                IncomeBand::Over110k->value => 44000,
            ],
            'application_system' => 'the Common App',
            'application_url' => 'https://www.commonapp.org/',
            'deadline_month' => 1,
            'deadline_day' => 15,
            'application_fee' => 60,
            'fee_waiver_available' => true,
        ];
    }

    /**
     * An open-admission school: no published range to compare a GPA against.
     */
    public function openAdmission(): static
    {
        return $this->state(fn () => [
            'kind' => CollegeKind::Community,
            'control' => CollegeControl::Public,
            'gpa_25th' => null,
            'gpa_75th' => null,
            'acceptance_rate' => 1.0,
            'tuition_in_state' => 4200,
            'tuition_out_of_state' => 9800,
            'room_and_board' => 0,
            'application_fee' => 0,
        ]);
    }

    public function selective(): static
    {
        return $this->state(fn () => ['gpa_25th' => 3.90, 'gpa_75th' => 4.00, 'acceptance_rate' => 0.06]);
    }
}
