<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Max Knowledge Snippets
    |--------------------------------------------------------------------------
    |
    | Maximum number of knowledge entries to include in the LLM context.
    |
    */
    'max_knowledge_snippets' => (int) env('ASSISTANT_MAX_KNOWLEDGE_SNIPPETS', 20),

    /*
    |--------------------------------------------------------------------------
    | Default System Policy
    |--------------------------------------------------------------------------
    |
    | Default system prompt when no custom prompt is configured in the database.
    |
    */
    'default_system_policy' => env('ASSISTANT_DEFAULT_SYSTEM_POLICY', 
        'You are an in-app assistant for this company system. Help the user understand features, workflows, permissions, and how to complete tasks. You must follow the user\'s permissions and only use provided knowledge snippets. If something is not in the snippets or is restricted, say so and suggest where in the system or who to ask (admin/manager) to get access. Do not invent data. Do not provide sensitive info. Provide step-by-step guidance when possible.'
    ),
];

