<?php

return [
    'sections' => [
        'sales' => [
            'label' => 'Sales',
            'permissions_any' => [
                'sales.dashboard.view',
                'sales.projects.view',
                'sales.reservations.view',
                'sales.targets.view',
                'sales.attendance.view',
            ],
            'tabs' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => '/sales/dashboard',
                    'permissions_any' => ['sales.dashboard.view'],
                ],
                'projects' => [
                    'label' => 'Projects',
                    'route' => '/sales/projects',
                    'permissions_any' => ['sales.projects.view'],
                ],
                'reservations' => [
                    'label' => 'Reservations',
                    'route' => '/sales/reservations',
                    'permissions_any' => ['sales.reservations.view'],
                ],
                'targets' => [
                    'label' => 'Targets',
                    'route' => '/sales/targets/my',
                    'permissions_any' => ['sales.targets.view'],
                ],
                'attendance' => [
                    'label' => 'Attendance',
                    'route' => '/sales/attendance/my',
                    'permissions_any' => ['sales.attendance.view'],
                ],
            ],
            'actions' => [
                'create_reservation' => ['permissions_any' => ['sales.reservations.create']],
                'confirm_reservation' => ['permissions_any' => ['sales.reservations.confirm']],
                'cancel_reservation' => ['permissions_any' => ['sales.reservations.cancel']],
                'manage_team' => ['permissions_any' => ['sales.team.manage']],
                'manage_payment_plans' => ['permissions_any' => ['sales.payment-plan.manage']],
            ],
        ],
        'hr' => [
            'label' => 'HR',
            'permissions_any' => [
                'hr.dashboard.view',
                'hr.teams.view',
                'hr.teams.manage',
                'hr.employees.manage',
                'hr.reports.view',
            ],
            'tabs' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => '/hr/dashboard',
                    'permissions_any' => ['hr.dashboard.view'],
                ],
                'teams' => [
                    'label' => 'Teams',
                    'route' => '/hr/teams',
                    'permissions_any' => ['hr.teams.view', 'hr.teams.manage'],
                ],
                'employees' => [
                    'label' => 'Employees',
                    'route' => '/hr/users',
                    'permissions_any' => ['hr.users.view', 'hr.employees.manage'],
                ],
                'reports' => [
                    'label' => 'Reports',
                    'route' => '/hr/reports',
                    'permissions_any' => ['hr.reports.view'],
                ],
            ],
            'actions' => [
                'manage_employees' => ['permissions_any' => ['hr.employees.manage']],
                'manage_teams' => ['permissions_any' => ['hr.teams.manage']],
                'manage_warnings' => ['permissions_any' => ['hr.warnings.manage']],
            ],
        ],
        'marketing' => [
            'label' => 'Marketing',
            'permissions_any' => [
                'marketing.dashboard.view',
                'marketing.projects.view',
                'marketing.tasks.view',
                'marketing.reports.view',
                'marketing.ads.view',
            ],
            'tabs' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => '/marketing/dashboard',
                    'permissions_any' => ['marketing.dashboard.view'],
                ],
                'projects' => [
                    'label' => 'Projects',
                    'route' => '/marketing/projects',
                    'permissions_any' => ['marketing.projects.view'],
                ],
                'tasks' => [
                    'label' => 'Tasks',
                    'route' => '/marketing/tasks',
                    'permissions_any' => ['marketing.tasks.view'],
                ],
                'reports' => [
                    'label' => 'Reports',
                    'route' => '/marketing/reports',
                    'permissions_any' => ['marketing.reports.view'],
                ],
                'ads' => [
                    'label' => 'Ads',
                    'route' => '/ads/insights',
                    'permissions_any' => ['marketing.ads.view'],
                ],
            ],
            'actions' => [
                'manage_budgets' => ['permissions_any' => ['marketing.budgets.manage']],
                'manage_tasks' => ['permissions_any' => ['marketing.tasks.confirm']],
                'manage_ads' => ['permissions_any' => ['marketing.ads.manage']],
            ],
        ],
        'credit' => [
            'label' => 'Credit',
            'permissions_any' => [
                'credit.dashboard.view',
                'credit.bookings.view',
                'credit.financing.view',
                'credit.claim_files.view',
            ],
            'tabs' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => '/credit/dashboard',
                    'permissions_any' => ['credit.dashboard.view'],
                ],
                'bookings' => [
                    'label' => 'Bookings',
                    'route' => '/credit/bookings',
                    'permissions_any' => ['credit.bookings.view'],
                ],
                'financing' => [
                    'label' => 'Financing',
                    'route' => '/credit/bookings/:id/financing',
                    'permissions_any' => ['credit.financing.view', 'credit.financing.manage'],
                ],
                'claim_files' => [
                    'label' => 'Claim Files',
                    'route' => '/credit/claim-files',
                    'permissions_any' => ['credit.claim_files.view'],
                ],
            ],
            'actions' => [
                'manage_bookings' => ['permissions_any' => ['credit.bookings.manage']],
                'manage_financing' => ['permissions_any' => ['credit.financing.manage']],
                'manage_title_transfer' => ['permissions_any' => ['credit.title_transfer.manage']],
                'manage_claim_files' => ['permissions_any' => ['credit.claim_files.manage']],
                'manage_payment_plans' => ['permissions_any' => ['credit.payment_plan.manage']],
            ],
        ],
        'accounting_finance' => [
            'label' => 'Accounting & Finance',
            'permissions_any' => [
                'accounting.dashboard.view',
                'accounting.claim_files.view',
                'accounting.sold-units.view',
                'accounting.deposits.view',
                'accounting.salaries.view',
            ],
            'tabs' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => '/accounting/dashboard',
                    'permissions_any' => ['accounting.dashboard.view'],
                ],
                'sold_units' => [
                    'label' => 'Sold Units',
                    'route' => '/accounting/sold-units',
                    'permissions_any' => ['accounting.sold-units.view'],
                ],
                'deposits' => [
                    'label' => 'Deposits',
                    'route' => '/accounting/deposits/pending',
                    'permissions_any' => ['accounting.deposits.view'],
                ],
                'salaries' => [
                    'label' => 'Salaries',
                    'route' => '/accounting/salaries',
                    'permissions_any' => ['accounting.salaries.view'],
                ],
            ],
            'actions' => [
                'manage_deposits' => ['permissions_any' => ['accounting.deposits.manage']],
                'approve_commissions' => ['permissions_any' => ['accounting.commissions.approve']],
                'distribute_salaries' => ['permissions_any' => ['accounting.salaries.distribute']],
                'manage_claim_files' => ['permissions_any' => ['accounting.claim_files.manage']],
            ],
        ],
        'contracts_projects' => [
            'label' => 'Contracts & Projects',
            'permissions_any' => [
                'contracts.view',
                'contracts.view_all',
                'projects.view',
                'exclusive_projects.view',
            ],
            'tabs' => [
                'contracts' => [
                    'label' => 'Contracts',
                    'route' => '/contracts/index',
                    'permissions_any' => ['contracts.view', 'contracts.view_all'],
                ],
                'project_management' => [
                    'label' => 'Project Management',
                    'route' => '/project_management/dashboard',
                    'permissions_any' => ['projects.view', 'dashboard.analytics.view'],
                ],
                'exclusive_projects' => [
                    'label' => 'Exclusive Projects',
                    'route' => '/exclusive-projects',
                    'permissions_any' => ['exclusive_projects.view', 'exclusive_projects.request'],
                ],
            ],
            'actions' => [
                'create_contract' => ['permissions_any' => ['contracts.create']],
                'approve_contract' => ['permissions_any' => ['contracts.approve']],
                'request_exclusive_project' => ['permissions_any' => ['exclusive_projects.request']],
            ],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'permissions_any' => [
                'units.view',
                'units.edit',
                'units.csv_upload',
            ],
            'tabs' => [
                'units' => [
                    'label' => 'Units',
                    'route' => '/inventory/contracts/units',
                    'permissions_any' => ['units.view'],
                ],
                'inventory_dashboard' => [
                    'label' => 'Inventory Dashboard',
                    'route' => '/inventory/dashboard',
                    'permissions_any' => ['contracts.view_all'],
                ],
            ],
            'actions' => [
                'edit_units' => ['permissions_any' => ['units.edit']],
                'upload_units_csv' => ['permissions_any' => ['units.csv_upload']],
            ],
        ],
        'ai_knowledge' => [
            'label' => 'AI & Knowledge',
            'permissions_any' => [
                'use-ai-assistant',
                'ai-calls.manage',
            ],
            'tabs' => [
                'assistant' => [
                    'label' => 'Assistant',
                    'route' => '/ai/chat',
                    'permissions_any' => ['use-ai-assistant'],
                ],
                'calls' => [
                    'label' => 'AI Calls',
                    'route' => '/ai/calls',
                    'permissions_any' => ['ai-calls.manage'],
                ],
            ],
            'actions' => [
                'manage_calls' => ['permissions_any' => ['ai-calls.manage']],
            ],
        ],
        'requests_workflow' => [
            'label' => 'Requests & Workflow',
            'permissions_any' => [
                'tasks.create',
            ],
            'tabs' => [
                'my_tasks' => [
                    'label' => 'My Tasks',
                    'route' => '/my-tasks',
                    'permissions_any' => ['tasks.create'],
                ],
                'requested_tasks' => [
                    'label' => 'Requested Tasks',
                    'route' => '/requested-tasks',
                    'permissions_any' => ['tasks.create'],
                ],
            ],
            'actions' => [
                'create_task' => ['permissions_any' => ['tasks.create']],
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'permissions_any' => [
                'notifications.view',
                'accounting.notifications.view',
            ],
            'tabs' => [
                'inbox' => [
                    'label' => 'Inbox',
                    'route' => '/notifications',
                    'permissions_any' => ['notifications.view', 'accounting.notifications.view'],
                ],
            ],
            'actions' => [
                'view_notifications' => ['permissions_any' => ['notifications.view', 'accounting.notifications.view']],
            ],
        ],
        'teams' => [
            'label' => 'Teams',
            'permissions_any' => [
                'sales.team.manage',
                'hr.teams.manage',
                'projects.team.allocate',
            ],
            'tabs' => [
                'team_management' => [
                    'label' => 'Team Management',
                    'route' => '/teams/index',
                    'permissions_any' => ['sales.team.manage', 'hr.teams.manage', 'projects.team.allocate'],
                ],
            ],
            'actions' => [
                'assign_team_members' => ['permissions_any' => ['hr.teams.manage', 'projects.team.allocate']],
            ],
        ],
        'developers' => [
            'label' => 'Developers',
            'permissions_any' => [
                'contracts.view',
                'contracts.view_all',
            ],
            'tabs' => [
                'directory' => [
                    'label' => 'Developer Directory',
                    'route' => '/developers',
                    'permissions_any' => ['contracts.view', 'contracts.view_all'],
                ],
            ],
            'actions' => [
                'view_developers' => ['permissions_any' => ['contracts.view', 'contracts.view_all']],
            ],
        ],
    ],
];
