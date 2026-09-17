<?php

namespace App\Ai\Concerns;

use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Attributes\Timeout as TimeoutAttribute;
use Laravel\Ai\Enums\Lab;
use ReflectionClass;
use RuntimeException;

/**
 * Lets the whole engine be pointed at a different model without editing it.
 *
 * `Promptable::getProvidersAndModels()` prefers a `provider()`/`model()` method
 * over the `#[Provider]`/`#[Model]` attributes, so a trait is the one seam that
 * reaches every call site at once. Threading `provider:` through each
 * `->prompt()` in `AnalyzeAssessmentJob` would cover only the calls someone
 * remembered, and a run where two of five layers quietly stayed on the cloud
 * proves nothing about either arm.
 *
 * The attributes remain the single declaration of each agent's default — this
 * reads them back rather than restating them, so there is no second place for
 * the default to drift.
 */
trait RunsOnTheConfiguredEngine
{
    public function provider(): string
    {
        return $this->configuredProvider() ?? $this->declared(ProviderAttribute::class, 'Provider');
    }

    public function model(): string
    {
        $configured = (string) (config('vocation.engine.model') ?? '');

        if ($configured !== '') {
            return $configured;
        }

        /*
         | A provider override with no model override would send this agent's
         | declared model name — a Claude checkpoint — to whatever else was
         | configured. Ollama answers an unknown model with a 404 that reads
         | like a network fault, so this is raised here where the cause is
         | still legible.
         */
        if ($this->configuredProvider() !== null) {
            throw new RuntimeException(
                'VOCATION_ENGINE_PROVIDER is set without VOCATION_ENGINE_MODEL. '
                .'A provider override must name the model to run, because '
                .static::class.' declares "'.$this->declared(ModelAttribute::class, 'Model').'", '
                .'which only its own provider serves.'
            );
        }

        return $this->declared(ModelAttribute::class, 'Model');
    }

    /**
     * A locally hosted model changes the latency budget by an order of
     * magnitude — measured at ~23 tokens/second against `llama3.2:3b`, where
     * this engine's largest phase emits seventeen scored categories. The
     * declared timeouts are correct for the provider they were written for,
     * so the longer bound applies only to a run that has been deliberately
     * pointed somewhere else. Production keeps its tight one.
     */
    public function timeout(): int
    {
        if ($this->configuredProvider() === null) {
            return $this->declaredTimeout();
        }

        return (int) config('vocation.engine.timeout', 900);
    }

    /**
     * Ollama's Prism handler defaults `num_predict` to 2048 when no
     * MaxTokens attribute is set, and merges provider options *after* that
     * default, so this is where it can be raised. 2048 is well under what
     * this engine's largest phase emits: the model produced valid-looking
     * JSON that simply stopped mid-object, the parse returned nothing, and
     * the failure read as "missing required field" several layers away.
     *
     * `num_ctx` matters for the same reason from the other side — Ollama
     * defaults to a 4096-token window, which the instructions alone nearly
     * fill, leaving no room for the answer.
     *
     * Keyed on the provider, so a cloud run is not affected by any of it.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if ((is_string($provider) ? $provider : $provider->value) !== 'ollama') {
            return [];
        }

        return [
            'num_predict' => (int) config('vocation.engine.max_output_tokens', 8192),
            'num_ctx' => (int) config('vocation.engine.context_tokens', 32768),
        ];
    }

    protected function declaredTimeout(): int
    {
        $attributes = (new ReflectionClass($this))->getAttributes(TimeoutAttribute::class);

        return $attributes === [] ? 60 : $attributes[0]->newInstance()->value;
    }

    protected function configuredProvider(): ?string
    {
        $configured = (string) (config('vocation.engine.provider') ?? '');

        return $configured === '' ? null : $configured;
    }

    /**
     * @param  class-string  $attribute
     */
    protected function declared(string $attribute, string $label): string
    {
        $attributes = (new ReflectionClass($this))->getAttributes($attribute);

        if ($attributes === []) {
            throw new RuntimeException(static::class.' uses '.__TRAIT__.' but declares no #['.$label.'].');
        }

        $value = $attributes[0]->newInstance()->value;

        return is_string($value) ? $value : $value->value;
    }
}
