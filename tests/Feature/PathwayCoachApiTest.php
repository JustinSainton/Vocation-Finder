<?php

namespace Tests\Feature;

use App\Enums\GapType;
use App\Enums\HabitCadence;
use App\Models\FeatureFlag;
use App\Models\Gap;
use App\Models\ParentConsent;
use App\Models\User;
use App\Support\ActionQueue;
use App\Support\HabitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PathwayCoachApiTest extends TestCase
{
    use RefreshDatabase;

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    protected function student(): User
    {
        $student = User::factory()->create([
            'grade_level' => 11,
            'birthdate' => now()->subYears(16)->toDateString(),
        ]);

        ParentConsent::create([
            'user_id' => $student->id,
            'parent_name' => 'A parent',
            'parent_email' => 'parent@example.com',
        ])->grant();

        return $student->fresh();
    }

    protected function gap(User $user): Gap
    {
        return Gap::create([
            'user_id' => $user->id,
            'type' => GapType::Information,
            'summary' => 'Has never seen the work up close.',
        ]);
    }

    #[Test]
    public function state_returns_action_readiness_and_habits_in_words(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $gap = $this->gap($student);
        (new ActionQueue)->assign($student, 'Ask your counselor about shadow day', 'Seeing the work settles the question faster than thinking about it.', $gap);
        (new HabitTracker)->prescribe($student, $gap, 'Write down one thing you noticed', HabitCadence::Daily, 'Attention compounds.');

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/v1/coach/state');

        $response->assertOk();
        $response->assertJsonPath('current_action.title', 'Ask your counselor about shadow day');
        $response->assertJsonStructure(['readiness' => ['level_label', 'what_moves_it']]);
        $response->assertJsonPath('habits.0.title', 'Write down one thing you noticed');
        $response->assertJsonPath('habits.0.next_move', (new HabitTracker)->forStudent($student)[0]['next_move']);

        $payload = $response->json();
        $this->assertArrayNotHasKey('kept', $payload['habits'][0]);
        $this->assertArrayNotHasKey('streak', $payload['habits'][0]);
    }

    #[Test]
    public function state_is_closed_without_entitlement(): void
    {
        $this->enableCoach();
        $freshman = User::factory()->create([
            'grade_level' => 9,
            'birthdate' => now()->subYears(14)->toDateString(),
        ]);

        $this->actingAs($freshman, 'sanctum')->getJson('/api/v1/coach/state')->assertForbidden();
    }

    #[Test]
    public function completing_the_action_clears_the_current_one(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $action = (new ActionQueue)->assign($student, 'Ask your counselor about shadow day');

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/v1/actions/{$action->id}/complete", ['reflection' => 'She said yes.']);

        $response->assertOk();
        $response->assertJsonPath('current_action', null);
        $this->assertSame('completed', $action->fresh()->status->value);
    }

    #[Test]
    public function nobody_settles_another_students_action(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $other = $this->student();
        $action = (new ActionQueue)->assign($other, 'Ask your counselor about shadow day');

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/v1/actions/{$action->id}/complete")
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/v1/actions/{$action->id}/skip")
            ->assertForbidden();
    }

    #[Test]
    public function habit_check_in_is_the_students_act_alone(): void
    {
        $this->enableCoach();
        $student = $this->student();
        $habit = (new HabitTracker)->prescribe($student, $this->gap($student), 'Write down one thing you noticed', HabitCadence::Daily);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/check-in", ['happened' => true])
            ->assertCreated();

        $stranger = $this->student();
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/check-in", ['happened' => true])
            ->assertForbidden();
    }

    #[Test]
    public function message_requires_words(): void
    {
        $this->enableCoach();
        $student = $this->student();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/coach/message', [])
            ->assertUnprocessable();
    }
}
