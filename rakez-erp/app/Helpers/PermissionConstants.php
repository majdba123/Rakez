<?php

namespace App\Helpers;

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

    // Sales
    const SALES_DASHBOARD_VIEW = 'sales.dashboard.view';
    const SALES_PROJECTS_VIEW = 'sales.projects.view';
    const SALES_RESERVATIONS_CREATE = 'sales.reservations.create';
    const SALES_RESERVATIONS_VIEW = 'sales.reservations.view';
    const SALES_RESERVATIONS_CONFIRM = 'sales.reservations.confirm';
    const SALES_RESERVATIONS_CANCEL = 'sales.reservations.cancel';
    const SALES_TARGETS_VIEW = 'sales.targets.view';
    const SALES_TARGETS_UPDATE = 'sales.targets.update';
    const SALES_TEAM_MANAGE = 'sales.team.manage';
    const SALES_ATTENDANCE_VIEW = 'sales.attendance.view';
    const SALES_ATTENDANCE_MANAGE = 'sales.attendance.manage';
    const SALES_TASKS_MANAGE = 'sales.tasks.manage';
}
