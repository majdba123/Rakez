<?php

return [
    'enabled' => env('AI_ENABLED', true),
    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),

        // Responses API prefers max_output_tokens (output only)
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1000),

        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 30),

        // Helps avoid "context too long" errors
        'truncation' => env('OPENAI_TRUNCATION', 'auto'),

        // Privacy-safe identifier (recommend hashing user id in code if you want)
        'safety_identifier_prefix' => env('OPENAI_SAFETY_ID_PREFIX', 'erp_user:'),
    ],

    'chat' => [
        // Create a summary after N messages
        'summary_every' => (int) env('AI_SUMMARY_EVERY', 12),

        // How many recent messages to keep in the prompt
        'tail_messages' => (int) env('AI_TAIL_MESSAGES', 6),

        // If you want a separate summary window:
        'summary_window' => (int) env('AI_SUMMARY_WINDOW', 12),
    ],

    'retries' => [
        'max_attempts' => (int) env('AI_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 500),
        'max_delay_ms' => (int) env('AI_RETRY_MAX_DELAY_MS', 5000),
        'jitter_ms' => (int) env('AI_RETRY_JITTER_MS', 250),
    ],

    'rate_limits' => [
        'per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 60),
    ],
    'budgets' => [
        'per_user_daily_tokens' => (int) env('AI_DAILY_TOKEN_BUDGET', 0),
    ],
    'retention' => [
        'days' => (int) env('AI_RETENTION_DAYS', 90),
    ],
];
