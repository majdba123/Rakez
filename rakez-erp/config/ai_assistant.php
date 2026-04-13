<?php

return [
    'enabled' => env('AI_ENABLED', true),
    'default_provider' => env('AI_PROVIDER', 'openai'),
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
        // Keep this finite by default so new environments do not ship with unlimited token spend.
        'per_user_daily_tokens' => (int) env('AI_DAILY_TOKEN_BUDGET', 20000),
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
        'sections' => ['marketing', 'sales', 'accounting', 'hr'],
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
        /** Explicit per-tool gates so tool exposure stays aligned with domain permissions. */
        'tool_gates' => [
            'tool_search_records' => ['permission' => 'use-ai-assistant'],
            'tool_get_lead_summary' => ['permission' => 'leads.view'],
            'tool_get_project_summary' => ['permission' => 'contracts.view'],
            'tool_get_contract_status' => ['permission' => 'contracts.view'],
            'tool_kpi_sales' => ['permission' => 'sales.dashboard.view'],
            'tool_explain_access' => ['permission' => 'use-ai-assistant'],
            'tool_rag_search' => ['permission' => 'use-ai-assistant'],
            'tool_campaign_advisor' => ['permission' => 'marketing.dashboard.view'],
            'tool_hiring_advisor' => ['permission' => 'hr.dashboard.view'],
            'tool_finance_calculator' => ['permission' => 'use-ai-assistant'],
            'tool_marketing_analytics' => ['permission' => 'marketing.dashboard.view'],
            /** ERP snapshots; handler enforces contracts.view / sales.reservations.view per topic. */
            'tool_sales_advisor' => ['permission' => 'sales.dashboard.view'],
            'tool_ai_call_status' => ['permission' => 'ai-calls.manage'],
        ],
    ],

    /**
     * Arabic-first UX copy for the assistant (Saudi business context).
     * Technical identifiers (permissions, routes) may remain Latin where needed.
     */
    'messages' => [
        'empty_response' => 'تعذّر إنشاء ردّ الآن. يُرجى إعادة المحاولة.',
        'conversation_summary_label' => 'ملخص المحادثة:',
        'assistant_llm_error' => 'عذرًا، حدث خطأ أثناء المعالجة. يُرجى المحاولة لاحقًا.',
        'budget_exceeded' => 'تم تجاوز حدّ الرموز اليومي (%1$d/%2$d). يُرجى المحاولة لاحقًا.',
        'orchestrator' => [
            'could_not_complete' => 'تعذّر إكمال طلبك.',
            'parse_failed' => 'تعذّر تحليل مخرجات النموذج.',
            'tool_limit' => 'تم بلوغ الحدّ الأقصى لاستدعاءات الأدوات في هذه الجلسة.',
            'generic_error' => 'حدث خطأ. يُرجى إعادة المحاولة.',
        ],
    ],
];
