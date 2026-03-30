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

    'embeddings' => [
        'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('OPENAI_EMBEDDING_BATCH_SIZE', 100),
    ],

    'rag' => [
        'chunk_max_tokens' => (int) env('AI_RAG_CHUNK_MAX_TOKENS', 500),
        'chunk_overlap_tokens' => (int) env('AI_RAG_CHUNK_OVERLAP_TOKENS', 50),
        'search_limit' => (int) env('AI_RAG_SEARCH_LIMIT', 5),
        'min_similarity' => (float) env('AI_RAG_MIN_SIMILARITY', 0.7),
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 5),
        'timeout_seconds' => (int) env('AI_CB_TIMEOUT_SECONDS', 60),
        'half_open_max_attempts' => (int) env('AI_CB_HALF_OPEN_MAX', 3),
    ],

    'smart_rate_limits' => [
        'admin' => 120,
        'sales_leader' => 60,
        'sales' => 30,
        'marketing' => 30,
        'default' => 15,
    ],

    'tools' => [
        'orchestrated_chat' => (bool) env('AI_TOOLS_ORCHESTRATED_CHAT', true),
        'sections' => ['marketing', 'sales', 'finance', 'hr'],
    ],

    'v2' => [
        'openai' => [
            'model' => env('AI_V2_MODEL', 'gpt-4.1-mini'),
            'temperature' => (float) env('AI_V2_TEMPERATURE', 0.0),
            'max_output_tokens' => (int) env('AI_V2_MAX_OUTPUT_TOKENS', 2000),
            'truncation_strategy' => env('AI_V2_TRUNCATION', 'auto'),
        ],
        'tool_loop' => [
            'max_tool_calls' => (int) env('AI_V2_MAX_TOOL_CALLS', 6),
        ],
        /** Optional per-tool gates; tools not listed default to use-ai-assistant only. */
        'tool_gates' => [
            'tool_kpi_sales' => ['permission' => 'sales.dashboard.view'],
        ],
    ],
];
