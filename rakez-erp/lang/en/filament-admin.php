<?php

return [
    'panel' => [
        'brand_name' => 'Rakez Governance',
    ],

    'navigation' => [
        'groups' => [
            'overview' => 'Overview',
            'access_governance' => 'Access Governance',
            'governance_observability' => 'Governance Observability',
            'credit_oversight' => 'Credit Oversight',
            'accounting_finance' => 'Accounting & Finance',
            'contracts_projects' => 'Contracts & Projects',
            'sales_oversight' => 'Sales Oversight',
            'hr_oversight' => 'HR Oversight',
            'marketing_oversight' => 'Marketing Oversight',
            'inventory_oversight' => 'Inventory Oversight',
            'ai_knowledge' => 'AI & Knowledge',
            'requests_workflow' => 'Requests & Workflow',
        ],
    ],

    'stepper' => [
        'state' => [
            'completed' => 'Completed',
            'current' => 'Current',
            'failed' => 'Needs Attention',
            'pending' => 'Pending',
            'skipped' => 'Skipped',
        ],
    ],

    'role_aliases' => [
        'admin' => 'Admin',
        'legacy_admin' => 'Legacy Admin',
    ],

    'status' => [
        'sales_target' => [
            'new' => 'New',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ],
        'marketing_task' => [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ],
    ],

    'resources' => [
        'users' => [
            'navigation_label' => 'Users',
            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'password' => 'Password',
                'type' => 'User Type',
                'manager' => 'Manager',
                'team' => 'Team',
                'active' => 'Active',
                'additional_roles' => 'Additional Roles',
                'admin_roles' => 'Admin Roles',
                'direct_permissions' => 'Direct Permissions',
                'effective_access' => 'Effective Access Snapshot',
            ],
            'columns' => [
                'name' => 'Name',
                'email' => 'Email',
                'type' => 'Type',
                'manager' => 'Manager',
                'team' => 'Team',
                'governance_roles' => 'Governance Roles',
                'direct_permissions' => 'Direct Permissions',
                'active' => 'Active',
                'deleted' => 'Deleted',
            ],
            'helper' => [
                'legacy_admin' => 'Legacy admin type is preserved for compatibility but cannot be newly assigned here.',
                'additional_roles' => 'Optional operational roles alongside the primary role derived from user type.',
                'admin_roles' => 'Only admin authority can assign the top-level admin role.',
                'direct_permissions' => 'Grant or revoke individual permissions directly for this user.',
                'effective_access_after_create' => 'Available after creating the user.',
            ],
        ],

        'roles' => [
            'navigation_label' => 'Roles',
            'fields' => [
                'name' => 'Role',
                'category' => 'Category',
                'permissions' => 'Permissions',
            ],
            'columns' => [
                'name' => 'Role',
                'users' => 'Users',
                'permissions' => 'Permissions',
                'category' => 'Category',
            ],
            'category' => [
                'legacy_operational' => 'Legacy operational role',
                'governance_overlay' => 'Governance overlay role',
                'future_section' => 'Future governance section role',
                'system' => 'System role',
                'legacy' => 'Legacy',
                'governance' => 'Governance',
                'future_section_short' => 'Future Section',
                'system_short' => 'System',
            ],
            'helper' => [
                'permissions' => 'Permissions are sourced from the governance dictionary and DB-backed permissions table.',
            ],
        ],

        'permissions' => [
            'navigation_label' => 'Permissions',
            'columns' => [
                'name' => 'Permission',
                'description' => 'Description',
                'roles' => 'Roles',
                'direct_users' => 'Direct Users',
                'guard' => 'Guard',
            ],
        ],

        'direct_permissions' => [
            'navigation_label' => 'Direct Permissions',
            'fields' => [
                'user' => 'User',
                'email' => 'Email',
                'current_roles' => 'Current Roles',
                'direct_permissions' => 'Direct Permissions',
                'effective_access' => 'Effective Access Snapshot',
            ],
            'columns' => [
                'name' => 'User',
                'email' => 'Email',
                'direct_permissions' => 'Direct Permissions',
                'roles' => 'Roles',
                'panel_access' => 'Panel Access',
                'deleted' => 'Deleted',
            ],
        ],

        'effective_access' => [
            'summary' => [
                'legacy_roles' => 'Legacy roles',
                'governance_roles' => 'Governance roles',
                'direct_permissions' => 'Direct permissions',
                'inherited_permissions' => 'Inherited permissions',
                'temporary_permissions' => 'Temporary permissions',
                'dynamic_permissions' => 'Dynamic permissions',
                'panel_eligible' => 'Panel eligible',
                'yes' => 'yes',
                'no' => 'no',
                'none' => 'none',
            ],
        ],

        'credit_notifications' => [
            'navigation_label' => 'Credit Notifications',
            'columns' => [
                'recipient' => 'Recipient',
                'event' => 'Event',
            ],
            'status' => [
                'pending' => 'Pending',
                'read' => 'Read',
            ],
            'actions' => [
                'mark_read' => 'Mark Read',
                'mark_all_read' => 'Mark All Read',
            ],
            'sections' => [
                'review' => 'Notification Review',
                'context' => 'Context',
            ],
            'notifications' => [
                'marked_read' => 'Credit notification marked as read.',
                'all_marked_read' => 'All credit notifications marked as read.',
            ],
        ],

        'workflow_tasks' => [
            'navigation_label' => 'Workflow Tasks',
            'columns' => [
                'task' => 'Task',
                'team' => 'Team',
                'assignee' => 'Assignee',
                'created_by' => 'Created By',
            ],
            'fields' => [
                'reason' => 'Reason',
                'due_at' => 'Due At',
            ],
            'status' => [
                'in_progress' => 'In Progress',
                'completed' => 'Completed',
                'could_not_complete' => 'Could Not Complete',
            ],
            'actions' => [
                'create' => 'Create Task',
                'mark_in_progress' => 'Mark In Progress',
                'mark_completed' => 'Mark Completed',
                'could_not_complete' => 'Could Not Complete',
            ],
            'helper' => [
                'section' => 'Optional. Defaults to the assignee user type.',
            ],
            'notifications' => [
                'created' => 'Task created successfully.',
                'moved_in_progress' => 'Task moved back to in progress.',
                'marked_completed' => 'Task marked as completed.',
                'marked_not_completable' => 'Task marked as not completable.',
            ],
        ],

        'title_transfers' => [
            'navigation_label' => 'Title Transfers',
            'columns' => [
                'booking' => 'Booking',
                'project' => 'Project',
                'unit' => 'Unit',
                'processed_by' => 'Processed By',
                'created' => 'Created',
            ],
            'entries' => [
                'booking_id' => 'Booking ID',
                'client' => 'Client',
                'credit_status' => 'Credit Status',
            ],
            'status' => [
                'preparation' => 'Preparation',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
            ],
            'actions' => [
                'schedule' => 'Schedule',
                'clear_schedule' => 'Clear Schedule',
                'complete' => 'Complete',
            ],
            'sections' => [
                'stepper' => 'Transfer Progress',
                'review' => 'Transfer Review',
                'reservation' => 'Reservation',
            ],
            'stepper' => [
                'title' => 'Title Transfer Lifecycle',
                'steps' => [
                    'preparation' => 'Preparation',
                    'scheduled' => 'Scheduled',
                    'completed' => 'Completed',
                ],
            ],
            'notifications' => [
                'scheduled' => 'Title transfer scheduled.',
                'cleared' => 'Title transfer schedule cleared.',
                'completed' => 'Title transfer completed.',
            ],
        ],

        'claim_files' => [
            'actions' => [
                'generate_bulk' => 'Generate Bulk Claim Files',
                'generate_combined' => 'Generate Combined Claim File',
            ],
            'fields' => [
                'sold_bookings' => 'Sold Bookings',
                'claim_type_commission' => 'Commission',
            ],
            'notifications' => [
                'bulk_generated' => 'Bulk claim files generated.',
                'combined_generated' => 'Combined claim file generated.',
            ],
        ],

        'sales_targets' => [
            'actions' => [
                'set_status' => 'Set Status',
            ],
            'fields' => [
                'status' => 'Status',
            ],
            'notifications' => [
                'status_updated' => 'Sales target status updated.',
            ],
        ],

        'marketing_tasks' => [
            'actions' => [
                'delete' => 'Delete',
                'mark_completed' => 'Mark Completed',
            ],
            'notifications' => [
                'deleted' => 'Marketing task deleted.',
                'completed' => 'Marketing task completed.',
            ],
        ],

        'employee_contracts' => [
            'navigation_label' => 'Employee Contracts',
            'columns' => [
                'employee' => 'Employee',
                'pdf' => 'PDF',
                'remaining_days' => 'Remaining Days',
                'lifecycle' => 'Lifecycle',
            ],
            'status' => [
                'draft' => 'Draft',
                'active' => 'Active',
                'expired' => 'Expired',
                'terminated' => 'Terminated',
            ],
            'actions' => [
                'create' => 'Create Contract',
                'edit' => 'Edit',
                'generate_pdf' => 'Generate PDF',
                'activate' => 'Activate',
                'terminate' => 'Terminate',
                'lifecycle' => 'Lifecycle',
            ],
            'modals' => [
                'lifecycle_heading' => 'Contract Lifecycle',
                'close' => 'Close',
            ],
            'stepper' => [
                'steps' => [
                    'draft' => 'Draft',
                    'active' => 'Active',
                    'expired' => 'Expired',
                    'terminated' => 'Terminated',
                ],
            ],
            'notifications' => [
                'created' => 'Employee contract created.',
                'updated' => 'Employee contract updated.',
                'pdf_generated' => 'Employee contract PDF generated.',
                'activated' => 'Employee contract activated.',
                'terminated' => 'Employee contract terminated.',
            ],
        ],

        'credit_bookings' => [
            'navigation_label' => 'Credit Bookings',
            'columns' => [
                'project' => 'Project',
                'unit' => 'Unit',
                'client' => 'Client',
                'credit_status' => 'Credit Status',
                'purchase' => 'Purchase',
                'deposit_confirmed' => 'Deposit Confirmed',
                'financing' => 'Financing',
                'title_transfer' => 'Title Transfer',
                'claim_file' => 'Claim File',
            ],
            'status' => [
                'confirmed' => 'Confirmed',
                'under_negotiation' => 'Under Negotiation',
                'cancelled' => 'Cancelled',
            ],
            'credit_status' => [
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'title_transfer' => 'Title Transfer',
                'sold' => 'Sold',
                'rejected' => 'Rejected',
            ],
            'financing_status' => [
                'completed' => 'Completed',
            ],
            'purchase' => [
                'cash' => 'Cash',
                'supported_bank' => 'Supported Bank',
                'unsupported_bank' => 'Unsupported Bank',
            ],
            'employment' => [
                'government' => 'Government',
                'private' => 'Private',
            ],
            'filters' => [
                'financing_status' => 'Financing Status',
                'has_title_transfer' => 'Has Title Transfer',
                'has_claim_file' => 'Has Claim File',
            ],
            'actions' => [
                'edit_client' => 'Edit Client',
                'log_contact' => 'Log Contact',
                'cancel' => 'Cancel',
                'advance_financing' => 'Advance Financing',
                'reject_financing' => 'Reject Financing',
                'generate_claim_file' => 'Generate Claim File',
                'generate_claim_pdf' => 'Generate Claim PDF',
            ],
            'sections' => [
                'process_progress' => 'Process Progress',
                'reservation' => 'Reservation',
                'client_financial' => 'Client and Financials',
                'financing' => 'Financing',
                'transfer_claim' => 'Transfer and Claim Review',
            ],
            'entries' => [
                'booking_id' => 'Booking ID',
                'confirmed_at' => 'Confirmed At',
                'mobile' => 'Mobile',
                'nationality' => 'Nationality',
                'iban' => 'IBAN',
                'down_payment' => 'Down Payment',
                'installments' => 'Installments',
                'remaining_payment_plan' => 'Remaining Payment Plan',
                'needs_accounting_confirmation' => 'Needs Accounting Confirmation',
                'overall_status' => 'Overall Status',
                'assigned_to' => 'Assigned To',
                'current_stage' => 'Current Stage',
                'remaining_days' => 'Remaining Days',
                'progress_summary' => 'Progress Summary',
                'no_financing_tracker' => 'No financing tracker',
                'title_transfer_status' => 'Title Transfer Status',
                'scheduled_date' => 'Scheduled Date',
                'completed_date' => 'Completed Date',
                'not_generated' => 'Not generated',
                'claim_pdf' => 'Claim PDF',
                'claim_amount' => 'Claim Amount',
            ],
            'notifications' => [
                'client_updated' => 'Booking client details updated.',
                'contact_logged' => 'Credit client contact logged.',
                'cancelled' => 'Booking cancelled.',
                'financing_advanced' => 'Financing workflow advanced.',
                'financing_rejected' => 'Financing request rejected.',
                'claim_file_generated' => 'Claim file generated.',
                'claim_pdf_ready' => 'Claim file PDF ready.',
            ],
            'stepper' => [
                'reservation_title' => 'Reservation Lifecycle',
                'financing_title' => 'Financing Stages',
                'transfer_title' => 'Title Transfer',
                'financing_not_required' => 'Financing is not required for this booking',
                'financing_not_started' => 'Financing tracker is not initialized',
                'transfer_not_started' => 'Title transfer not started',
                'steps' => [
                    'confirmed' => 'Booking Confirmed',
                    'financing' => 'Financing',
                    'title_transfer' => 'Title Transfer',
                    'sold' => 'Sold',
                ],
                'stages' => [
                    'stage' => 'Stage :number',
                ],
                'transfer_steps' => [
                    'preparation' => 'Preparation',
                    'scheduled' => 'Scheduled',
                    'completed' => 'Completed',
                ],
            ],
            'stage' => [
                'label' => 'Stage :number',
                'value_with_deadline' => ':status (deadline :deadline)',
            ],
        ],
    ],
];
