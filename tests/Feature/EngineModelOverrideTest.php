<?php

namespace Tests\Feature;

use App\Ai\Agents\CategoryDisambiguation;
use App\Ai\Agents\ConversationAgent;
use App\Ai\Agents\NarrativeSynthesis;
use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Agents\SignalDetection;
use App\Ai\Agents\VocationalAnalysis;
use App\Models\Assessment;
use App\Models\ParentConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * The engine must be runnable on a model that is not a third party's.
 *
 * Every agent declares a provider and a model in its own attributes, which is
 * the right default and the wrong thing to have to edit six times to answer
 * "what does this engine do on a model we host ourselves?". The override
 * exists so that question can be asked without touching the engine being
 * asked about.
 *
 * These tests assert the wiring, not the answer. What a local model actually
 * produces is the live corpus run's job; this is what makes that run possible
 * and, more importantly, honest — a run where two of five layers quietly
 * stayed on the cloud would prove nothing about either arm.
 */
class EngineModelOverrideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, object>
     */
    protected function engineAgents(): array
    {
        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        return [
            new VocationalAnalysis($assessment),
            new NarrativeSynthesis([]),
            new SignalDetection([]),
            new CategoryDisambiguation([], []),
            new ConversationAgent('Question?', 'An answer.', []),
            new PathwayCoachAgent($this->consentedJunior()),
        ];
    }

    /**
     * The coach refuses to exist for a student who is not entitled to one, so
     * even a wiring test needs a real one.
     */
    protected function consentedJunior(): User
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

    #[Test]
    public function every_engine_agent_keeps_its_declared_default_when_nothing_is_configured(): void
    {
        config(['vocation.engine.provider' => null, 'vocation.engine.model' => null]);

        foreach ($this->engineAgents() as $agent) {
            $reflection = new ReflectionClass($agent);

            $this->assertSame(
                $reflection->getAttributes(Provider::class)[0]->newInstance()->value,
                $agent->provider(),
                $reflection->getShortName().' stopped honouring its own #[Provider].',
            );

            $this->assertSame(
                $reflection->getAttributes(Model::class)[0]->newInstance()->value,
                $agent->model(),
                $reflection->getShortName().' stopped honouring its own #[Model].',
            );
        }
    }

    /**
     * The whole point: one setting moves the entire engine, not the layers
     * someone remembered to thread a parameter through.
     */
    #[Test]
    public function one_setting_moves_every_layer_of_the_engine_at_once(): void
    {
        config(['vocation.engine.provider' => 'ollama', 'vocation.engine.model' => 'llama3.2:3b']);

        foreach ($this->engineAgents() as $agent) {
            $this->assertSame('ollama', $agent->provider(), $agent::class.' stayed on its declared provider.');
            $this->assertSame('llama3.2:3b', $agent->model(), $agent::class.' stayed on its declared model.');
        }
    }

    /**
     * Ollama answers an unknown model with a 404 that reads like a network
     * fault, so a half-configured override would look like the local server
     * being down rather than like a typo in an env file.
     */
    #[Test]
    public function a_provider_without_a_model_is_refused_where_the_cause_is_still_legible(): void
    {
        config(['vocation.engine.provider' => 'ollama', 'vocation.engine.model' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('VOCATION_ENGINE_PROVIDER is set without VOCATION_ENGINE_MODEL');

        (new VocationalAnalysis(Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ])))->model();
    }

    /**
     * A model override alone is meaningful — a different Claude checkpoint —
     * and must not drag the provider with it.
     */
    #[Test]
    public function a_model_may_be_changed_without_changing_the_provider(): void
    {
        config(['vocation.engine.provider' => null, 'vocation.engine.model' => 'claude-haiku-4-5-20251001']);

        $agent = new VocationalAnalysis(Assessment::create([
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]));

        $this->assertSame('anthropic', $agent->provider());
        $this->assertSame('claude-haiku-4-5-20251001', $agent->model());
    }
}
