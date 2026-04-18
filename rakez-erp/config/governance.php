<?php

return [
    'panel_id' => 'admin',
    'panel_path' => 'admin',
    'panel_locale' => env('GOVERNANCE_PANEL_LOCALE', 'ar'),

    'panel_access_permission' => 'admin.panel.access',
    'super_admin_role' => 'super_admin',
    /*
    | Role slug aliases for business-facing semantics.
    | Internal DB/security checks keep using canonical slugs.
    */
    'role_slug_aliases' => [
        'super_admin' => 'admin',
        'admin' => 'legacy_admin',
    ],
    /*
    | Filament panel entry authority.
    | Only these roles can enter /admin.
    */
    'panel_authority_roles' => [
        'super_admin',
    ],
    /*
    | Governance roles managed by Access Governance services.
    | These may power API/service authorization without panel entry authority.
    */
    'managed_governance_roles' => [
        'admin',
        'erp_admin',
        'super_admin',
        'auditor_readonly',
        'credit_admin',
        'accounting_admin',
        'projects_admin',
        'sales_admin',
        'hr_admin',
        'marketing_admin',
        'inventory_admin',
        'ai_admin',
        'workflow_admin',
    ],
    /*
    | Deprecated alias kept for legacy callers.
    */
    'managed_panel_roles' => [
        'super_admin',
    ],
    'future_section_roles' => [
    ],
    'operational_roles' => [
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
    | Time-bound temporary grants (Access Governance → Temporary Permissions).
    | Disable with GOVERNANCE_TEMPORARY_PERMISSIONS_ENABLED=false if rollout is not desired.
    */
    'temporary_permissions' => [
        'enabled' => (bool) env('GOVERNANCE_TEMPORARY_PERMISSIONS_ENABLED', true),
    ],

    /*
    | Production rollout enables every governance-safe Filament section.
    | These labels must stay aligned with AdminPanelProvider navigationGroups()
    | and the group strings used by overview pages/resources.
    */
    'enabled_sections' => [
        'Overview',
        'Access Governance',
        'Governance Observability',
        'Credit Oversight',
        'Accounting & Finance',
        'Contracts & Projects',
        'Sales Oversight',
        'HR Oversight',
        'Marketing Oversight',
        'Inventory Oversight',
        'AI & Knowledge',
        'Requests & Workflow',
    ],

    /*
    | Filament: business oversight navigation groups require at least one of these
    | permissions (in addition to each resource's own view gate via canViewAny).
    | Groups omitted here rely only on per-resource permissions (Overview, Access
    | Governance, Governance Observability).
    */
    'filament_navigation_group_permissions' => [
        'Credit Oversight' => ['credit.dashboard.view'],
        'Accounting & Finance' => ['accounting.dashboard.view'],
        'Contracts & Projects' => ['contracts.view_all', 'exclusive_projects.view', 'projects.view'],
        'Sales Oversight' => ['sales.dashboard.view'],
        'HR Oversight' => ['hr.dashboard.view'],
        'Marketing Oversight' => ['marketing.dashboard.view'],
        'Inventory Oversight' => ['units.view'],
        'AI & Knowledge' => ['ai.knowledge.view', 'ai.calls.view'],
        'Requests & Workflow' => [
            'governance.oversight.workflow.view',
            'governance.approvals.center.view',
        ],
    ],
];
