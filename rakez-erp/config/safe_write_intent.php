<?php

/**
 * Pre-execution intent gate for SafeWriteActionService::confirm only.
 * Deny by default. Does not perform mutations or business authorization.
 */
return [
    /*
    | Master switch: when false, no action may pass the intent gate.
    */
    'enabled' => (bool) env('SAFE_WRITE_INTENT_ENABLED', false),

    /*
    | Explicit allowlist of registry action keys that may ever pass the gate
    | when all other conditions are satisfied.
    */
    'allowlisted_keys' => [
        'credit_booking.client_contact.log',
        'sales_reservation.action.log',
    ],

    /*
    | Per-action feature flags (all default false).
    */
    'actions' => [
        'credit_booking.client_contact.log' => (bool) env('SAFE_WRITE_INTENT_CREDIT_CLIENT_CONTACT_LOG', false),
        'sales_reservation.action.log' => (bool) env('SAFE_WRITE_INTENT_SALES_RESERVATION_ACTION_LOG', false),
    ],
];
