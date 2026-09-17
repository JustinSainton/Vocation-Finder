<?php

use App\Support\Nudges\SilentChannel;

return [
    /*
    |--------------------------------------------------------------------------
    | AI Analysis Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'model' => env('AI_MODEL', 'claude-sonnet-4-6'),
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
    | Engine Model Override
    |--------------------------------------------------------------------------
    |
    | Unset, every agent runs on the provider and model it declares in its own
    | attributes. Set, all of them run on this one instead — which is how the
    | golden corpus is driven through a local model without editing the engine
    | it is supposed to be testing. Both values must be given together.
    |
    */

    'engine' => [
        'provider' => env('VOCATION_ENGINE_PROVIDER'),
        'model' => env('VOCATION_ENGINE_MODEL'),
        'timeout' => env('VOCATION_ENGINE_TIMEOUT', 900),
        'max_output_tokens' => env('VOCATION_ENGINE_MAX_OUTPUT_TOKENS', 8192),
        'context_tokens' => env('VOCATION_ENGINE_CONTEXT_TOKENS', 32768),
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
        'analysis_dispatch' => env('ASSESSMENT_ANALYSIS_DISPATCH', 'after_response'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Results Delivery Configuration
    |--------------------------------------------------------------------------
    */
    'results' => [
        'email_dispatch' => env('RESULTS_EMAIL_DISPATCH', 'after_response'),
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
        'transcription_secondary_model' => env('TRANSCRIPTION_SECONDARY_MODEL', 'whisper-1'),
        'transcription_fallback_provider' => env('TRANSCRIPTION_FALLBACK_PROVIDER'),
        'transcription_fallback_model' => env('TRANSCRIPTION_FALLBACK_MODEL'),
        'transcription_retry_attempts' => env('TRANSCRIPTION_RETRY_ATTEMPTS', 2),
        'transcription_retry_delay_ms' => env('TRANSCRIPTION_RETRY_DELAY_MS', 700),
        'recording_audio_disk' => env('RECORDING_AUDIO_DISK', 's3'),
        'recording_audio_fallback_disk' => env('RECORDING_AUDIO_FALLBACK_DISK', 'public'),
        'max_audio_size_kb' => 10240,
        'recording_sample_rate' => 16000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Beta Configuration
    |--------------------------------------------------------------------------
    |
    | When "questions_enabled" is true, the API serves the abbreviated 5-question
    | beta set (Question::where('is_beta', true)). When false, it serves the full
    | 20-question assessment (the is_beta=false set seeded by QuestionSeeder).
    | Defaults to false so the complete assessment is the standard experience;
    | set BETA_QUESTIONS_ENABLED=true to fall back to the short beta set.
    |
    */
    'beta' => [
        'questions_enabled' => env('BETA_QUESTIONS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Tier Limits
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Nudges — bringing a student back
    |--------------------------------------------------------------------------
    |
    | A stub on purpose. Roadmap 3.6 (SMS) was deferred; the likely first real
    | driver is a push notification or an iOS Live Activity through the Expo
    | app rather than a text message. The *rules* around contacting a student
    | are already settled and live in App\Support\Nudges\NudgeDispatcher, so
    | adding a carrier means implementing NudgeChannel and naming it here.
    |
    | The default is silence, not "whichever driver is registered". A stub that
    | goes live because somebody set an environment variable is the dangerous
    | kind, and an unknown channel name falls back to silence rather than
    | failing open.
    |
    */
    'nudges' => [
        'channel' => env('NUDGE_CHANNEL', 'silent'),

        'channels' => [
            'silent' => SilentChannel::class,
        ],
    ],

    'free_tier' => [
        'analysis_model' => env('FREE_TIER_MODEL', 'claude-haiku-4-5-20251001'),
        'output_sections' => ['opening_synthesis', 'vocational_orientation'],
    ],
];
