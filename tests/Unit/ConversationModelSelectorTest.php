<?php

namespace Tests\Unit;

use App\Services\Ai\ConversationModelSelector;
use Tests\TestCase;

class ConversationModelSelectorTest extends TestCase
{
    public function test_returns_control_when_experiment_is_disabled(): void
    {
        config()->set('vocation.ai.conversation_experiment', [
            'enabled' => false,
            'rollout_percentage' => 100,
            'force_variant' => null,
            'control' => [
                'provider' => 'anthropic',
                'model' => 'claude-haiku',
            ],
            'treatment' => [
                'provider' => 'openrouter',
                'model' => 'qwen-4b',
            ],
        ]);

        $selector = new ConversationModelSelector;
        $selected = $selector->forSessionId('session-abc');

        $this->assertSame('control', $selected['variant']);
        $this->assertSame('anthropic', $selected['provider']);
        $this->assertSame('claude-haiku', $selected['model']);
    }

    public function test_force_variant_overrides_rollout_assignment(): void
    {
        config()->set('vocation.ai.conversation_experiment', [
            'enabled' => true,
            'rollout_percentage' => 0,
            'force_variant' => 'treatment',
            'control' => [
                'provider' => 'anthropic',
                'model' => 'claude-haiku',
            ],
            'treatment' => [
                'provider' => 'openrouter',
                'model' => 'qwen-4b',
            ],
        ]);

        $selector = new ConversationModelSelector;
        $selected = $selector->forSessionId('session-xyz');

        $this->assertSame('treatment', $selected['variant']);
        $this->assertSame('openrouter', $selected['provider']);
        $this->assertSame('qwen-4b', $selected['model']);
    }

    public function test_assignment_is_deterministic_for_same_session_id(): void
    {
        config()->set('vocation.ai.conversation_experiment', [
            'enabled' => true,
            'rollout_percentage' => 50,
            'force_variant' => null,
            'control' => [
                'provider' => 'anthropic',
                'model' => 'claude-haiku',
            ],
            'treatment' => [
                'provider' => 'openrouter',
                'model' => 'qwen-4b',
            ],
        ]);

        $selector = new ConversationModelSelector;

        $first = $selector->forSessionId('stable-session-id-1');
        $second = $selector->forSessionId('stable-session-id-1');

        $this->assertSame($first['variant'], $second['variant']);
        $this->assertSame($first['provider'], $second['provider']);
        $this->assertSame($first['model'], $second['model']);
    }

    /**
     * The conversation path passes provider and model as explicit arguments,
     * which outrank the agent's own `model()`. Without this the engine
     * override would reach five layers and silently miss the sixth — and a
     * local run where one layer stayed on a third party's model is worse than
     * no local run, because it looks like one.
     */
    public function test_an_engine_override_outranks_the_experiment(): void
    {
        config()->set('vocation.ai.conversation_experiment', [
            'enabled' => true,
            'rollout_percentage' => 100,
            'force_variant' => 'treatment',
            'control' => ['provider' => 'anthropic', 'model' => 'claude-haiku'],
            'treatment' => ['provider' => 'openrouter', 'model' => 'qwen-4b'],
        ]);
        config()->set('vocation.engine.provider', 'ollama');
        config()->set('vocation.engine.model', 'llama3.2:3b');

        $selection = (new ConversationModelSelector)->forSessionId('any-session');

        $this->assertSame('ollama', $selection['provider']);
        $this->assertSame('llama3.2:3b', $selection['model']);

        // Not reported as an arm. A turn that ran on an override is not a
        // sample of either one, and counting it as the treatment would show
        // up later as a conclusion about a model that was never asked.
        $this->assertSame('engine_override', $selection['variant']);
        $this->assertFalse($selection['experiment_enabled']);
    }

    public function test_the_experiment_is_untouched_when_no_override_is_set(): void
    {
        config()->set('vocation.ai.conversation_experiment', [
            'enabled' => true,
            'rollout_percentage' => 100,
            'force_variant' => 'treatment',
            'control' => ['provider' => 'anthropic', 'model' => 'claude-haiku'],
            'treatment' => ['provider' => 'openrouter', 'model' => 'qwen-4b'],
        ]);
        config()->set('vocation.engine.provider', null);
        config()->set('vocation.engine.model', null);

        $selection = (new ConversationModelSelector)->forSessionId('any-session');

        $this->assertSame('treatment', $selection['variant']);
        $this->assertSame('openrouter', $selection['provider']);
    }
}
