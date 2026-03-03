<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Analysis Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'model' => env('AI_MODEL', 'claude-sonnet-4-20250514'),
        'model_lite' => env('AI_MODEL_LITE', 'claude-haiku-4-5-20251001'),
        'analysis_timeout' => env('AI_ANALYSIS_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assessment Configuration
    |--------------------------------------------------------------------------
    */
    'assessment' => [
        'guest_session_days' => 30,
        'data_retention_days' => 365 * 2,
        'autosave_debounce_ms' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audio Configuration
    |--------------------------------------------------------------------------
    */
    'audio' => [
        'tts_voice' => env('TTS_VOICE', 'nova'),
        'max_audio_size_kb' => 10240,
        'recording_sample_rate' => 16000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Tier Limits
    |--------------------------------------------------------------------------
    */
    'free_tier' => [
        'analysis_model' => env('FREE_TIER_MODEL', 'claude-haiku-4-5-20251001'),
        'output_sections' => ['opening_synthesis', 'vocational_orientation'],
    ],
];
