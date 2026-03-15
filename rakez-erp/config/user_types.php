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
        0 => 'marketing',
        1 => 'admin',
        2 => 'project_acquisition',
        3 => 'project_management',
        4 => 'editor',
        5 => 'sales',
        6 => 'accounting',
        7 => 'credit',
        8 => 'hr',
        9 => 'inventory',
    ],

    /*
    |--------------------------------------------------------------------------
    | All Valid User Types (string values stored in DB)
    |--------------------------------------------------------------------------
    */
    'all' => [
        'marketing',
        'admin',
        'project_acquisition',
        'project_management',
        'editor',
        'sales',
        'accounting',
        'credit',
        'hr',
        'inventory',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valid Numeric IDs for validation
    |--------------------------------------------------------------------------
    */
    'valid_ids' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],

    /*
    |--------------------------------------------------------------------------
    | Allowed types per middleware (for reference - middleware use these)
    |--------------------------------------------------------------------------
    */
    'middleware_allowed' => [
        'hr' => ['hr', 'admin'],
        'inventory' => ['inventory', 'admin'],
        'marketing' => ['marketing', 'admin'],
        'sales' => ['sales', 'admin'],
    ],
];
