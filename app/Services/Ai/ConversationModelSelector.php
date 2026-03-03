<?php

namespace App\Services\Ai;

class ConversationModelSelector
{
    /**
     * Resolve the assigned experiment arm and model configuration for a session.
     *
     * @return array{
     *     variant: 'control'|'treatment',
     *     provider: ?string,
     *     model: ?string,
     *     rollout_percentage: int,
     *     experiment_enabled: bool
     * }
     */
    public function forSessionId(string $sessionId): array
    {
        /** @var array<string, mixed> $config */
        $config = config('vocation.ai.conversation_experiment', []);

        $enabled = (bool) ($config['enabled'] ?? false);
        $rolloutPercentage = $this->normalizePercentage((int) ($config['rollout_percentage'] ?? 0));
        $forcedVariant = $this->normalizeVariant($config['force_variant'] ?? null);

        $variant = $forcedVariant
            ?? $this->variantFromRollout($sessionId, $enabled, $rolloutPercentage);

        /** @var array<string, mixed> $arm */
        $arm = $variant === 'treatment'
            ? ($config['treatment'] ?? [])
            : ($config['control'] ?? []);

        return [
            'variant' => $variant,
            'provider' => $this->nullableString($arm['provider'] ?? null),
            'model' => $this->nullableString($arm['model'] ?? null),
            'rollout_percentage' => $rolloutPercentage,
            'experiment_enabled' => $enabled,
        ];
    }

    protected function variantFromRollout(string $sessionId, bool $enabled, int $rolloutPercentage): string
    {
        if (! $enabled || $rolloutPercentage <= 0) {
            return 'control';
        }

        $bucket = (int) sprintf('%u', crc32($sessionId)) % 100;

        return $bucket < $rolloutPercentage ? 'treatment' : 'control';
    }

    protected function normalizePercentage(int $value): int
    {
        return max(0, min(100, $value));
    }

    /**
     * @return 'control'|'treatment'|null
     */
    protected function normalizeVariant(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['control', 'treatment'], true)
            ? $normalized
            : null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
