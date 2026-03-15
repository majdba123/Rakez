<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Type Mapping (numeric => string)
    |--------------------------------------------------------------------------
    | Used for registration, filtering, and validation across the project.
    | Keep in sync with: register service, RegisterUser, UpdateUser, HrUserController.
    */
    'numeric_map' => [
        1 => 'admin',
        2 => 'project_management',
        3 => 'editor',
        4 => 'developer',
        5 => 'marketing',
        6 => 'sales',
        7 => 'sales_leader',
        8 => 'hr',
        9 => 'credit',
        10 => 'accounting',
        11 => 'inventory',
        12 => 'default',
        13 => 'accountant',
    ],

    /*
    |--------------------------------------------------------------------------
    | All Valid User Types (string values stored in DB)
    |--------------------------------------------------------------------------
    */
    'all' => [
        'admin',
        'project_management',
        'editor',
        'developer',
        'marketing',
        'sales',
        'sales_leader',
        'hr',
        'credit',
        'accounting',
        'inventory',
        'default',
        'accountant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valid Numeric IDs for validation
    |--------------------------------------------------------------------------
    */
    'valid_ids' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],

    /*
    |--------------------------------------------------------------------------
    | Allowed types per middleware (for reference - middleware use these)
    |--------------------------------------------------------------------------
    */
    'middleware_allowed' => [
        'hr' => ['hr', 'admin'],
        'inventory' => ['inventory', 'admin'],
        'marketing' => ['marketing', 'admin'],
        'sales' => ['sales', 'sales_leader', 'admin'],
    ],
];
