<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            /*
            | Server-side publishing (MessageSent, etc.): PHP → Reverb HTTP API.
            | Must use the host/port where Reverb actually listens (usually loopback + http).
            | Do not use the public API domain here — https://api.example:8080 often has no TLS and times out (cURL 28).
            | Override with REVERB_INTERNAL_HOST, REVERB_INTERNAL_PORT, REVERB_INTERNAL_SCHEME if needed (e.g. Docker service name).
            */
            'options' => [
                'host' => env('REVERB_INTERNAL_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_INTERNAL_PORT', env('REVERB_SERVER_PORT', 8080)),
                'scheme' => env('REVERB_INTERNAL_SCHEME', 'http'),
                'useTLS' => env('REVERB_INTERNAL_SCHEME', 'http') === 'https',
            ],
            /*
            | Browser WebSocket (Blade + pusher-js). Must match resources/js/bootstrap.js (VITE_REVERB_*).
            | REVERB_PORT is usually the Reverb process port (8080); browsers often use 443 + Nginx → Reverb.
            | Prefer VITE_REVERB_HOST / VITE_REVERB_PORT / VITE_REVERB_SCHEME so notification pages match Echo/chat.
            */
            'frontend' => [
                'host' => env('VITE_REVERB_HOST', env('REVERB_HOST', 'localhost')),
                'port' => is_numeric(env('VITE_REVERB_PORT'))
                    ? (int) env('VITE_REVERB_PORT')
                    : (env('REVERB_SCHEME', 'https') === 'https'
                        ? 443
                        : (int) env('REVERB_PORT', 8080)),
                'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')),
                'useTLS' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
