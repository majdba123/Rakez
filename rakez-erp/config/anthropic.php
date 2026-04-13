<?php

return [
    'enabled' => env('ANTHROPIC_ENABLED', false),
    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_url' => env('ANTHROPIC_BASE_URL'),
    'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
    'temperature' => (float) env('ANTHROPIC_TEMPERATURE', 0.7),
    'max_output_tokens' => (int) env('ANTHROPIC_MAX_OUTPUT_TOKENS', 1000),
    'timeout' => (float) env('ANTHROPIC_REQUEST_TIMEOUT', 30),
    'allow_user_override' => (bool) env('ANTHROPIC_ALLOW_USER_OVERRIDE', false),
    'user_id_prefix' => env('ANTHROPIC_USER_ID_PREFIX', 'erp_user:'),
    'retries' => [
        'max_attempts' => (int) env('ANTHROPIC_RETRY_MAX_ATTEMPTS', 3),
        'initial_delay_seconds' => (float) env('ANTHROPIC_RETRY_INITIAL_DELAY_SECONDS', 0.5),
        'max_delay_seconds' => (float) env('ANTHROPIC_RETRY_MAX_DELAY_SECONDS', 8.0),
    ],
];
