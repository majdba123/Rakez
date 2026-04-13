<?php

return [
    'enabled' => env('AI_REALTIME_ENABLED', false),

    'openai' => [
        'model' => env('OPENAI_REALTIME_MODEL', 'gpt-realtime'),
        'voice' => env('OPENAI_REALTIME_VOICE', 'marin'),
        'input_audio_format' => env('OPENAI_REALTIME_INPUT_AUDIO_FORMAT', 'audio/pcm'),
        'output_audio_format' => env('OPENAI_REALTIME_OUTPUT_AUDIO_FORMAT', 'audio/pcm'),
        'turn_detection' => env('OPENAI_REALTIME_TURN_DETECTION', 'semantic_vad'),
        'client_secrets_endpoint' => env('OPENAI_REALTIME_CLIENT_SECRETS_ENDPOINT', 'https://api.openai.com/v1/realtime/client_secrets'),
    ],

    'sessions' => [
        'max_duration_seconds' => (int) env('AI_REALTIME_MAX_DURATION_SECONDS', 900),
        'max_active_sessions_per_user' => (int) env('AI_REALTIME_MAX_ACTIVE_SESSIONS_PER_USER', 1),
        'max_reconnects' => (int) env('AI_REALTIME_MAX_RECONNECTS', 3),
        'transport_mode' => env('AI_REALTIME_TRANSPORT_MODE', 'control_plane_only'),
        'rollback_target' => env('AI_REALTIME_ROLLBACK_TARGET', 'voice_fallback'),
    ],

    'rate_limits' => [
        'session_create_per_minute' => (int) env('AI_REALTIME_CREATE_PER_MINUTE', 3),
        'control_events_per_minute' => (int) env('AI_REALTIME_CONTROL_EVENTS_PER_MINUTE', 60),
    ],

    'budgets' => [
        'estimated_max_session_tokens' => (int) env('AI_REALTIME_MAX_SESSION_TOKENS', 4000),
    ],

    'transport' => [
        'max_audio_append_bytes' => (int) env('AI_REALTIME_MAX_AUDIO_APPEND_BYTES', 262144),
        'max_client_event_payload_bytes' => (int) env('AI_REALTIME_MAX_CLIENT_EVENT_PAYLOAD_BYTES', 524288),
        'bridge_stale_after_seconds' => (int) env('AI_REALTIME_BRIDGE_STALE_AFTER_SECONDS', 30),
    ],
];
