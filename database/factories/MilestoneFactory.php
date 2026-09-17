<?php

namespace Database\Factories;

use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => MilestoneKind::Application,
            'status' => MilestoneStatus::NotYet,
            'title' => 'Send the application.',
            'why' => null,
            'due_on' => now()->addMonths(2)->toDateString(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => MilestoneStatus::Done]);
    }

    public function putDown(): static
    {
        return $this->state(fn () => ['status' => MilestoneStatus::PutDown]);
    }
}
