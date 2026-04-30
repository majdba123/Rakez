<?php

return [
    'waiting_list_expiry_days' => (int) env('SALES_WAITING_LIST_EXPIRY_DAYS', 30),

    'unit_search_alerts' => [
        'enabled' => env('SALES_UNIT_SEARCH_ALERTS_ENABLED', true),
        'sms_enabled' => env('SALES_UNIT_SEARCH_ALERT_SMS_ENABLED', false),
        /** Optional sender override; falls back to TWILIO_PHONE_NUMBER for Twilio-backed SMS. */
        'from_number' => env('SALES_UNIT_SEARCH_ALERT_FROM', env('TWILIO_PHONE_NUMBER')),
        'default_expiration_days' => (int) env('SALES_UNIT_SEARCH_ALERT_DEFAULT_EXPIRATION_DAYS', 30),
        'queue' => env('SALES_UNIT_SEARCH_ALERT_QUEUE', 'default'),
        'close_after_first_match' => env('SALES_UNIT_SEARCH_ALERT_CLOSE_AFTER_FIRST_MATCH', true),
        'throttle_minutes_per_alert' => (int) env('SALES_UNIT_SEARCH_ALERT_THROTTLE_MINUTES', 60),
        'delivery_status_callback_enabled' => env('SALES_UNIT_SEARCH_ALERT_DELIVERY_STATUS_CALLBACK_ENABLED', false),

        'saudi_policy' => [
            'require_sms_opt_in' => env('SALES_UNIT_SEARCH_ALERT_REQUIRE_SMS_OPT_IN', true),
            'require_registered_sender_id' => env('SALES_UNIT_SEARCH_ALERT_REQUIRE_REGISTERED_SENDER_ID', true),
            'block_urls' => env('SALES_UNIT_SEARCH_ALERT_BLOCK_URLS', true),
            'block_phone_numbers_in_body' => env('SALES_UNIT_SEARCH_ALERT_BLOCK_PHONE_NUMBERS_IN_BODY', true),
            'sending_window_enabled' => env('SALES_UNIT_SEARCH_ALERT_SENDING_WINDOW_ENABLED', false),
            'sending_window_start_hour' => (int) env('SALES_UNIT_SEARCH_ALERT_SENDING_WINDOW_START_HOUR', 9),
            'sending_window_end_hour' => (int) env('SALES_UNIT_SEARCH_ALERT_SENDING_WINDOW_END_HOUR', 21),
        ],
    ],
];
