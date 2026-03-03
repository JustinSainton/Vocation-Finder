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
        'conversation_experiment' => [
            'enabled' => env('CONVERSATION_MODEL_EXPERIMENT_ENABLED', false),
            'rollout_percentage' => env('CONVERSATION_MODEL_EXPERIMENT_ROLLOUT', 0),
            'force_variant' => env('CONVERSATION_MODEL_EXPERIMENT_FORCE_VARIANT'),
            'control' => [
                'provider' => env('CONVERSATION_MODEL_CONTROL_PROVIDER', 'anthropic'),
                'model' => env('CONVERSATION_MODEL_CONTROL', 'claude-haiku-4-5-20251001'),
            ],
            'treatment' => [
                'provider' => env('CONVERSATION_MODEL_TREATMENT_PROVIDER', 'openrouter'),
                'model' => env('CONVERSATION_MODEL_TREATMENT', 'qwen/qwen3-4b-instruct-2507'),
            ],
        ],
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
        'tts_provider' => env('TTS_PROVIDER', 'openai'),
        'tts_model' => env('TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice' => env('TTS_VOICE', 'nova'),
        'tts_fallback_provider' => env('TTS_FALLBACK_PROVIDER'),
        'tts_fallback_model' => env('TTS_FALLBACK_MODEL'),
        'tts_fallback_voice' => env('TTS_FALLBACK_VOICE', 'default-female'),
        'tts_instructions' => env(
            'TTS_INSTRUCTIONS',
            'Speak in a calm, grounded, lower register. Slow the pace slightly, with gentle pauses and a soothing, reassuring tone. Avoid corporate or sales-like cadence.'
        ),
        'tts_audio_disk' => env('TTS_AUDIO_DISK', 's3'),
        'tts_audio_fallback_disk' => env('TTS_AUDIO_FALLBACK_DISK', 'public'),
        'tts_audio_ttl_minutes' => env('TTS_AUDIO_TTL_MINUTES', 15),
        'transcription_provider' => env('TRANSCRIPTION_PROVIDER', env('AI_TRANSCRIPTION_PROVIDER', 'openai')),
        'transcription_model' => env('TRANSCRIPTION_MODEL'),
        'transcription_fallback_provider' => env('TRANSCRIPTION_FALLBACK_PROVIDER'),
        'transcription_fallback_model' => env('TRANSCRIPTION_FALLBACK_MODEL'),
        'recording_audio_disk' => env('RECORDING_AUDIO_DISK', 's3'),
        'recording_audio_fallback_disk' => env('RECORDING_AUDIO_FALLBACK_DISK', 'public'),
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
