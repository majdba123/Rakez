<?php

namespace App\Constants;

class PermissionConstants
{
    // Contracts
    const CONTRACTS_VIEW = 'contracts.view';
    const CONTRACTS_VIEW_ALL = 'contracts.view_all';
    const CONTRACTS_CREATE = 'contracts.create';
    const CONTRACTS_APPROVE = 'contracts.approve';
    const CONTRACTS_DELETE = 'contracts.delete';

    // Units
    const UNITS_VIEW = 'units.view';
    const UNITS_EDIT = 'units.edit';
    const UNITS_CSV_UPLOAD = 'units.csv_upload';

    // Second Party
    const SECOND_PARTY_VIEW = 'second_party.view';
    const SECOND_PARTY_EDIT = 'second_party.edit';

    // Departments
    const DEPARTMENTS_BOARDS_VIEW = 'departments.boards.view';
    const DEPARTMENTS_BOARDS_EDIT = 'departments.boards.edit';
    const DEPARTMENTS_PHOTOGRAPHY_VIEW = 'departments.photography.view';
    const DEPARTMENTS_PHOTOGRAPHY_EDIT = 'departments.photography.edit';
    const DEPARTMENTS_MONTAGE_VIEW = 'departments.montage.view';
    const DEPARTMENTS_MONTAGE_EDIT = 'departments.montage.edit';

    // Dashboard
    const DASHBOARD_ANALYTICS_VIEW = 'dashboard.analytics.view';

    // Notifications
    const NOTIFICATIONS_VIEW = 'notifications.view';
    const NOTIFICATIONS_MANAGE = 'notifications.manage';

    // Employees
    const EMPLOYEES_MANAGE = 'employees.manage';

    // Project Management
    const PROJECTS_VIEW = 'projects.view';
    const PROJECTS_CREATE = 'projects.create';
    const PROJECTS_MEDIA_UPLOAD = 'projects.media.upload';
    const PROJECTS_MEDIA_APPROVE = 'projects.media.approve';
    const PROJECTS_TEAM_CREATE = 'projects.team.create';
    const PROJECTS_TEAM_ASSIGN_LEADER = 'projects.team.assign_leader';
    const PROJECTS_TEAM_ALLOCATE = 'projects.team.allocate';
    const PROJECTS_APPROVE = 'projects.approve';
    const PROJECTS_ARCHIVE = 'projects.archive';

    // Sales
    const SALES_DASHBOARD_VIEW = 'sales.dashboard.view';
    const SALES_PROJECTS_VIEW = 'sales.projects.view';
    const SALES_UNITS_VIEW = 'sales.units.view';
    const SALES_UNITS_BOOK = 'sales.units.book';
    const SALES_RESERVATIONS_CREATE = 'sales.reservations.create';
    const SALES_RESERVATIONS_VIEW = 'sales.reservations.view';
    const SALES_RESERVATIONS_CONFIRM = 'sales.reservations.confirm';
    const SALES_RESERVATIONS_CANCEL = 'sales.reservations.cancel';
    const SALES_WAITING_LIST_CREATE = 'sales.waiting_list.create';
    const SALES_WAITING_LIST_CONVERT = 'sales.waiting_list.convert';
    const SALES_GOALS_VIEW = 'sales.goals.view';
    const SALES_GOALS_CREATE = 'sales.goals.create';
    const SALES_SCHEDULE_VIEW = 'sales.schedule.view';
    const SALES_TARGETS_VIEW = 'sales.targets.view';
    const SALES_TARGETS_UPDATE = 'sales.targets.update';
    const SALES_TEAM_MANAGE = 'sales.team.manage';
    const SALES_ATTENDANCE_VIEW = 'sales.attendance.view';
    const SALES_ATTENDANCE_MANAGE = 'sales.attendance.manage';
    const SALES_TASKS_MANAGE = 'sales.tasks.manage';
    const SALES_TASKS_CREATE_FOR_MARKETING = 'sales.tasks.create_for_marketing';
    const SALES_PROJECTS_ALLOCATE_SHIFTS = 'sales.projects.allocate_shifts';
    const SALES_PROJECTS_ASSIGN = 'sales.projects.assign';

    // Editing
    const EDITING_PROJECTS_VIEW = 'editing.projects.view';
    const EDITING_MEDIA_UPLOAD = 'editing.media.upload';

    // HR
    const HR_EMPLOYEES_MANAGE = 'hr.employees.manage';
    const HR_USERS_CREATE = 'hr.users.create';
    const HR_PERFORMANCE_VIEW = 'hr.performance.view';
    const HR_REPORTS_PRINT = 'hr.reports.print';

    // Marketing
    const MARKETING_DASHBOARD_VIEW = 'marketing.dashboard.view';
    const MARKETING_PROJECTS_VIEW = 'marketing.projects.view';
    const MARKETING_PLANS_CREATE = 'marketing.plans.create';
    const MARKETING_BUDGETS_MANAGE = 'marketing.budgets.manage';
    const MARKETING_TASKS_VIEW = 'marketing.tasks.view';
    const MARKETING_TASKS_CONFIRM = 'marketing.tasks.confirm';
    const MARKETING_REPORTS_VIEW = 'marketing.reports.view';

    // Exclusive Projects
    const EXCLUSIVE_PROJECTS_REQUEST = 'exclusive_projects.request';
    const EXCLUSIVE_PROJECTS_APPROVE = 'exclusive_projects.approve';
    const EXCLUSIVE_PROJECTS_CONTRACT_COMPLETE = 'exclusive_projects.contract.complete';
    const EXCLUSIVE_PROJECTS_CONTRACT_EXPORT = 'exclusive_projects.contract.export';

    // AI Assistant
    const USE_AI_ASSISTANT = 'use-ai-assistant';
    const MANAGE_AI_KNOWLEDGE = 'manage-ai-knowledge';

    // Credit
    const CREDIT_DASHBOARD_VIEW = 'credit.dashboard.view';
    const CREDIT_BOOKINGS_VIEW = 'credit.bookings.view';
    const CREDIT_FINANCING_MANAGE = 'credit.financing.manage';
    const CREDIT_TITLE_TRANSFER_MANAGE = 'credit.title_transfer.manage';
    const CREDIT_CLAIM_FILES_GENERATE = 'credit.claim_files.generate';

    // Accounting
    const ACCOUNTING_DOWN_PAYMENT_CONFIRM = 'accounting.down_payment.confirm';
}
