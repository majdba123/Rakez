<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Collection;

$options = [
    'admin.users.view' => 'admin.users.view',
    'admin.users.manage' => 'admin.users.manage',
    'hr.employees.view' => 'hr.employees.view',
];

$grouped = collect($options)
    ->groupBy(function ($label, $permission) {
        return explode('.', $permission)[0];
    }, true)
    ->map(fn ($permissions) => $permissions->all())
    ->all();

var_export($grouped);
