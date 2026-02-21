<?php

/**
 * Curated list of UI routes for AI assistant suggested links.
 * Only routes listed here can be suggested; user must have required permission.
 * Single source of truth for explain-access suggested_routes.
 *
 * Format: route key (path or name) => [ 'label' => string, 'permission' => string|array ]
 */
return [
    'api/contracts/index' => [
        'label' => 'My Contracts',
        'permission' => 'contracts.view',
    ],
    'api/contracts/show' => [
        'label' => 'View Contract',
        'permission' => ['contracts.view', 'contracts.view_all'],
    ],
    'api/sales/dashboard' => [
        'label' => 'Sales Dashboard',
        'permission' => 'sales.dashboard.view',
    ],
    'api/sales/projects' => [
        'label' => 'Sales Projects',
        'permission' => 'sales.projects.view',
    ],
    'api/sales/reservations' => [
        'label' => 'Reservations',
        'permission' => 'sales.reservations.view',
    ],
    'api/marketing/dashboard' => [
        'label' => 'Marketing Dashboard',
        'permission' => 'marketing.dashboard.view',
    ],
    'api/marketing/projects' => [
        'label' => 'Marketing Projects',
        'permission' => 'marketing.projects.view',
    ],
    'api/marketing/leads' => [
        'label' => 'Leads',
        'permission' => 'marketing.projects.view',
    ],
    'api/marketing/tasks' => [
        'label' => 'Marketing Tasks',
        'permission' => 'marketing.tasks.view',
    ],
    'api/tasks' => [
        'label' => 'Task Management (إدارة المهام)',
        'permission' => 'tasks.create',
    ],
    'api/my-tasks' => [
        'label' => 'My Tasks (مهامي)',
        'permission' => 'tasks.create',
    ],
    'api/hr/dashboard' => [
        'label' => 'HR Dashboard',
        'permission' => 'hr.dashboard.view',
    ],
    'api/credit/dashboard' => [
        'label' => 'Credit Dashboard',
        'permission' => 'credit.dashboard.view',
    ],
    'api/accounting/dashboard' => [
        'label' => 'Accounting Dashboard',
        'permission' => 'accounting.dashboard.view',
    ],
];
