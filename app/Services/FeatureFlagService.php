<?php

namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_TTL_MINUTES = 5;

    public function isEnabled(string $key): bool
    {
        return Cache::remember(
            "feature_flag:{$key}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => FeatureFlag::where('key', $key)->value('is_enabled') ?? false
        );
    }

    public function allFlags(): Collection
    {
        return Cache::remember(
            'feature_flags:all',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => FeatureFlag::all()->pluck('is_enabled', 'key')
        );
    }

    public function toggle(string $key, bool $enabled): void
    {
        FeatureFlag::where('key', $key)->update(['is_enabled' => $enabled]);
        Cache::forget("feature_flag:{$key}");
        Cache::forget('feature_flags:all');
    }
}
