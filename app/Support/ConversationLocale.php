<?php

namespace App\Support;

class ConversationLocale
{
    public const DEFAULT = 'en-US';

    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return ['en-US', 'es-419', 'pt-BR'];
    }

    public static function normalize(?string $locale): string
    {
        if (! is_string($locale) || trim($locale) === '') {
            return self::DEFAULT;
        }

        $normalized = str_replace('_', '-', strtolower(trim($locale)));

        return match (true) {
            $normalized === 'en',
            $normalized === 'en-us',
            str_starts_with($normalized, 'en-') => 'en-US',

            $normalized === 'es',
            $normalized === 'es-419',
            $normalized === 'es-es',
            str_starts_with($normalized, 'es-') => 'es-419',

            $normalized === 'pt',
            $normalized === 'pt-br',
            $normalized === 'pt-pt',
            str_starts_with($normalized, 'pt-') => 'pt-BR',

            default => self::DEFAULT,
        };
    }

    public static function toLaravelLocale(string $locale): string
    {
        return match (self::normalize($locale)) {
            'es-419' => 'es_419',
            'pt-BR' => 'pt_BR',
            default => 'en',
        };
    }

    public static function displayName(string $locale): string
    {
        return match (self::normalize($locale)) {
            'es-419' => 'Spanish',
            'pt-BR' => 'Portuguese (Brazil)',
            default => 'English',
        };
    }

    public static function matches(string $candidate, string $target): bool
    {
        return self::normalize($candidate) === self::normalize($target);
    }
}
