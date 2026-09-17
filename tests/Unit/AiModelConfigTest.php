<?php

namespace Tests\Unit;

use Laravel\Ai\Attributes\Model;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guards against pinning a retired Anthropic model ID. A retired model returns
 * a non-retryable 404 at runtime, which silently breaks every AI job in
 * production while all faked-provider tests stay green. This locks the model
 * references in config and the AI agents to non-retired IDs.
 */
class AiModelConfigTest extends TestCase
{
    /**
     * Anthropic model IDs that have reached their retirement date and now 404.
     * Extend this as Anthropic publishes further retirements.
     *
     * @var list<string>
     */
    private const RETIRED_MODEL_IDS = [
        'claude-sonnet-4-20250514', // retired 2026-06-15
        'claude-opus-4-20250514',
        'claude-3-7-sonnet-20250219',
        'claude-3-5-haiku-20241022',
        'claude-3-opus-20240229',
        'claude-3-5-sonnet-20241022',
    ];

    #[Test]
    public function the_configured_analysis_models_are_not_retired(): void
    {
        foreach (['vocation.ai.model', 'vocation.ai.model_lite', 'vocation.free_tier.analysis_model'] as $key) {
            $this->assertNotContains(
                config($key),
                self::RETIRED_MODEL_IDS,
                "Config [{$key}] points at a retired model ID: ".config($key),
            );
        }
    }

    #[Test]
    public function no_ai_agent_pins_a_retired_model(): void
    {
        $agentFiles = glob(app_path('Ai/Agents/*.php'));
        $this->assertNotEmpty($agentFiles, 'Expected AI agent classes under app/Ai/Agents.');

        foreach ($agentFiles as $file) {
            $class = 'App\\Ai\\Agents\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class)) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(Model::class);

            foreach ($attributes as $attribute) {
                $modelId = $attribute->getArguments()[0] ?? null;

                $this->assertNotContains(
                    $modelId,
                    self::RETIRED_MODEL_IDS,
                    "{$class} pins a retired model ID: {$modelId}",
                );
            }
        }
    }
}
