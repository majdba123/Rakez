<?php

return [
    'enabled' => env('AI_CALLING_ENABLED', false),

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_PHONE_NUMBER'),
        'webhook_base_url' => env('TWILIO_WEBHOOK_BASE_URL', env('APP_URL')),
    ],

    'openai' => [
        'model' => env('AI_CALLING_MODEL', 'gpt-4.1-mini'),
        'max_tokens' => (int) env('AI_CALLING_MAX_TOKENS', 300),
        'temperature' => (float) env('AI_CALLING_TEMPERATURE', 0.3),
    ],

    'call' => [
        'max_duration_seconds' => (int) env('AI_CALL_MAX_DURATION', 600),
        'max_retries_per_question' => (int) env('AI_CALL_MAX_RETRIES', 2),
        'silence_timeout' => (int) env('AI_CALL_SILENCE_TIMEOUT', 5),
        'speech_timeout' => env('AI_CALL_SPEECH_TIMEOUT', 'auto'),
        'language' => env('AI_CALL_LANGUAGE', 'ar-SA'),
        'voice' => env('AI_CALL_VOICE', 'Polly.Zeina'),
        'max_concurrent_calls' => (int) env('AI_CALL_MAX_CONCURRENT', 5),
        'retry_delay_minutes' => (int) env('AI_CALL_RETRY_DELAY', 30),
        'max_call_attempts' => (int) env('AI_CALL_MAX_ATTEMPTS', 3),
    ],

    'bulk' => [
        'max_per_batch' => (int) env('AI_CALL_BULK_MAX', 50),
        'delay_between_calls_seconds' => (int) env('AI_CALL_BULK_DELAY', 10),
    ],
];
