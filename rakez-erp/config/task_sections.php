<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task section labels (for display in Add Task form)
    |--------------------------------------------------------------------------
    |
    | Maps user type (section value) to display label. Used by GET /api/tasks/sections.
    | Keys must match User.type. Missing keys fall back to the type value.
    |
    */

    'labels' => [
        'accounting' => 'قسم المحاسبة',
        'admin' => 'قسم الإدارة',
        'credit' => 'قسم الائتمان',
        'developer' => 'قسم التطوير',
        'editor' => 'قسم المونتاج',
        'hr' => 'قسم الموارد البشرية',
        'marketing' => 'قسم التسويق',
        'project_management' => 'قسم إدارة المشاريع',
        'sales' => 'قسم المبيعات',
        'user' => 'مستخدم',
    ],

];
