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
}
