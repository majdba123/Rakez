<?php
// CI/CD Auto Deploy Enabled

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Registration\RegisterController;
use App\Http\Controllers\Registration\LoginController;
use App\Http\Controllers\Registration\GoogleAuthController;
use App\Http\Controllers\Registration\FacebookController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FoodTypeProductProviderController;
use App\Http\Controllers\FoodTypeController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\ForgetPasswordController;
use App\Http\Controllers\Contract\ContractController;
use App\Http\Controllers\Contract\ContractInfoController;
use App\Http\Controllers\Contract\SecondPartyDataController;
use App\Http\Controllers\Contract\ContractUnitController;
use App\Http\Controllers\Contract\BoardsDepartmentController;
use App\Http\Controllers\Contract\PhotographyDepartmentController;
use App\Http\Controllers\Contract\MontageDepartmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Dashboard\ProjectManagementDashboardController;
use App\Http\Controllers\AI\AIAssistantController;
use App\Http\Controllers\AI\AssistantChatController;
use App\Http\Controllers\AI\AssistantKnowledgeController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Sales\SalesProjectController;
use App\Http\Controllers\Sales\SalesReservationController;
use App\Http\Controllers\Sales\SalesTargetController;
use App\Http\Controllers\Sales\SalesAttendanceController;
use App\Http\Controllers\Sales\MarketingTaskController;
use App\Http\Controllers\Sales\WaitingListController;
use App\Http\Controllers\Sales\NegotiationApprovalController;
use App\Http\Controllers\Sales\PaymentPlanController;
use App\Http\Controllers\ExclusiveProjectController;
use App\Http\Middleware\CheckDynamicPermission;

use App\Http\Controllers\HR\HrDashboardController;
use App\Http\Controllers\HR\HrTeamController;
use App\Http\Controllers\HR\MarketerPerformanceController;
use App\Http\Controllers\HR\HrUserController;
use App\Http\Controllers\HR\EmployeeWarningController;
use App\Http\Controllers\HR\EmployeeContractController;
use App\Http\Controllers\HR\HrReportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\Marketing\MarketingDashboardController;
use App\Http\Controllers\Marketing\MarketingProjectController;
use App\Http\Controllers\Marketing\DeveloperMarketingPlanController;
use App\Http\Controllers\Marketing\EmployeeMarketingPlanController;
use App\Http\Controllers\Marketing\ExpectedSalesController;
use App\Http\Controllers\Marketing\MarketingTaskController as MarketingModuleTaskController;
use App\Http\Controllers\Marketing\TeamManagementController;
use App\Http\Controllers\Marketing\LeadController;
use App\Http\Controllers\Marketing\MarketingReportController;
use App\Http\Controllers\Marketing\MarketingSettingsController;
use App\Http\Controllers\Credit\CreditDashboardController;
use App\Http\Controllers\Credit\CreditBookingController;
use App\Http\Controllers\Credit\CreditFinancingController;
use App\Http\Controllers\Credit\TitleTransferController;
use App\Http\Controllers\Credit\ClaimFileController;
use App\Http\Controllers\Accounting\AccountingConfirmationController;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\AccountingNotificationController;
use App\Http\Controllers\Accounting\AccountingCommissionController;
use App\Http\Controllers\Accounting\AccountingDepositController;
use App\Http\Controllers\Accounting\AccountingSalaryController;
use App\Http\Controllers\Dashboard\DashboardController;

use Illuminate\Support\Facades\File;  // أضف هذا السطر في الأعلى

// Broadcasting authentication route for API tokens
Broadcast::routes(['middleware' => ['auth:sanctum']]);



Route::post('/login', [LoginController::class, 'login']);





// Protected routes (auth required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LoginController::class, 'logout']);

    Route::prefix('ai')->middleware('throttle:ai-assistant')->group(function () {
        Route::post('/ask', [AIAssistantController::class, 'ask']);
        Route::post('/chat', [AIAssistantController::class, 'chat']);
        Route::get('/conversations', [AIAssistantController::class, 'conversations']);
        Route::delete('/conversations/{sessionId}', [AIAssistantController::class, 'deleteSession']);
        Route::get('/sections', [AIAssistantController::class, 'sections']);
    });

    // AI Help Assistant (Knowledge-based)
    Route::prefix('ai/assistant')->group(function () {
        // Chat endpoint - requires use-ai-assistant permission (checked in controller)
        Route::post('/chat', [AssistantChatController::class, 'chat']);

        // Knowledge CRUD - requires manage-ai-knowledge permission
        Route::middleware('permission:manage-ai-knowledge')->group(function () {
            Route::get('/knowledge', [AssistantKnowledgeController::class, 'index']);
            Route::post('/knowledge', [AssistantKnowledgeController::class, 'store']);
            Route::put('/knowledge/{id}', [AssistantKnowledgeController::class, 'update']);
            Route::delete('/knowledge/{id}', [AssistantKnowledgeController::class, 'destroy']);
        });
    });

    // Contract Routes - Protected routes (user contracts)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/contracts/index', [ContractController::class, 'index']);
        Route::post('/contracts/store', [ContractController::class, 'store']);
        Route::get('/contracts/show/{id}', [ContractController::class, 'show']);
        Route::put('/contracts/update/{id}', [ContractController::class, 'update']);
        Route::delete('/contracts/{id}', [ContractController::class, 'destroy']);

        Route::post('/contracts/store/info/{id}', [ContractInfoController::class, 'store']);


        Route::prefix('user/notifications')->group(function () {
            Route::get('/private', [NotificationController::class, 'getUserPrivateNotifications']);
            Route::get('/public', [NotificationController::class, 'getPublicNotifications']);
            Route::patch('/mark-all-read', [NotificationController::class, 'userMarkAllAsRead']);
            Route::patch('/{id}/read', [NotificationController::class, 'userMarkAsRead']);
        });
    });


    Route::middleware(['auth:sanctum', 'role:project_management|admin'])->group(function () {

        Route::get('/contracts/admin-index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
        Route::patch('contracts/update-status/{id}', [ContractController::class, 'projectManagementUpdateStatus'])->middleware('permission:contracts.approve');


        Route::prefix('second-party-data')->group(function () {
            Route::get('show/{id}', [SecondPartyDataController::class, 'show'])->middleware('permission:second_party.view');
            Route::post('store/{id}', [SecondPartyDataController::class, 'store'])->middleware('permission:second_party.edit');
            Route::put('update/{id}', [SecondPartyDataController::class, 'update'])->middleware('permission:second_party.edit');

            Route::get('/second-parties', [ContractInfoController::class, 'getAllSecondParties'])->middleware('permission:second_party.view');
            Route::get('/contracts-by-email', [ContractInfoController::class, 'getContractsBySecondPartyEmail'])->middleware('permission:second_party.view');
        });

        Route::prefix('contracts/units')->group(function () {
            Route::get('show/{contractId}', [ContractUnitController::class, 'indexByContract'])->middleware('permission:units.view');
            Route::post('upload-csv/{contractId}', [ContractUnitController::class, 'uploadCsvByContract'])->middleware('permission:units.csv_upload');
            Route::post('store/{contractId}', [ContractUnitController::class, 'store'])->middleware('permission:units.edit');
            Route::put('update/{unitId}', [ContractUnitController::class, 'update'])->middleware('permission:units.edit');
            Route::delete('delete/{unitId}', [ContractUnitController::class, 'destroy'])->middleware('permission:units.edit');
        });

        Route::prefix('boards-department')->group(function () {
            Route::get('show/{contractId}', [BoardsDepartmentController::class, 'show'])->middleware('permission:departments.boards.view');
            Route::post('store/{contractId}', [BoardsDepartmentController::class, 'store'])->middleware('permission:departments.boards.edit');
            Route::put('update/{contractId}', [BoardsDepartmentController::class, 'update'])->middleware('permission:departments.boards.edit');
        });

        Route::prefix('photography-department')->group(function () {
            Route::get('show/{contractId}', [PhotographyDepartmentController::class, 'show'])->middleware('permission:departments.photography.view');
            Route::post('store/{contractId}', [PhotographyDepartmentController::class, 'store'])->middleware('permission:departments.photography.edit');
            Route::put('update/{contractId}', [PhotographyDepartmentController::class, 'update'])->middleware('permission:departments.photography.edit');
            Route::patch('approve/{contractId}', [PhotographyDepartmentController::class, 'approve']);

        });

        // لوحة تحكم إدارة المشاريع - Project Management Dashboard
        Route::prefix('project_management/dashboard')->group(function () {
            Route::get('/', [ProjectManagementDashboardController::class, 'index'])->middleware('permission:dashboard.analytics.view');
            Route::get('/units-statistics', [ProjectManagementDashboardController::class, 'unitsStatistics'])->middleware('permission:dashboard.analytics.view');
        });




        Route::prefix('project_management')->group(function () {

            Route::prefix('teams')->group(function () {

                Route::get('/index', [TeamController::class, 'index']);
                Route::post('/store', [TeamController::class, 'store']);
                Route::put('/update/{id}', [TeamController::class, 'update']);
                Route::delete('/delete/{id}', [TeamController::class, 'destroy']);
                Route::get('/show/{id}', [TeamController::class, 'show']);

                Route::get('/index/{contractId}', [ContractController::class, 'getTeamsForContract_HR']);
                Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');

                Route::post('/add/{contractId}', [ContractController::class, 'addTeamsToContract']);
                Route::post('/remove/{contractId}', [ContractController::class, 'removeTeamsFromContract']);

                Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');

            });



        });

    });

    // ==========================================
    // EDITOR ROUTES - صلاحيات المحرر
    // ==========================================
    Route::prefix('editor')->middleware(['auth:sanctum', 'role:editor|admin'])->group(function () {

        // Contract routes for editor
        Route::prefix('contracts')->group(function () {
            Route::get('/index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
            Route::get('/show/{id}', [ContractController::class, 'show'])->middleware('permission:contracts.view');
        });

        // Montage Department - قسم المونتاج
        Route::prefix('montage-department')->group(function () {
            Route::get('show/{contractId}', [MontageDepartmentController::class, 'show'])->middleware('permission:departments.montage.view');
            Route::post('store/{contractId}', [MontageDepartmentController::class, 'store'])->middleware('permission:departments.montage.edit');
            Route::put('update/{contractId}', [MontageDepartmentController::class, 'update'])->middleware('permission:departments.montage.edit');
        });

    });

    // ==========================================
    // INVENTORY ROUTES - صلاحيات المخزون
    // ==========================================
    Route::prefix('inventory')->middleware(['auth:sanctum', 'inventory'])->group(function () {

        // Contracts
        Route::prefix('contracts')->group(function () {
            Route::get('/show/{id}', [ContractController::class, 'show']);
            Route::get('/admin-index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
            // Inventory dashboard (filters via query params)
            Route::get('/agency-overview', [ContractController::class, 'inventoryAgencyOverview'])->middleware('permission:contracts.view_all');
            // Locations (filters via query params)
            Route::get('/locations', [ContractController::class, 'locations'])->middleware('permission:contracts.view_all');
        });

        // Second party data
        Route::prefix('second-party-data')->group(function () {
            Route::get('/show/{id}', [SecondPartyDataController::class, 'show'])->middleware('permission:second_party.view');
        });

        // Contract units
        Route::prefix('contracts/units')->group(function () {
            Route::get('/show/{contractId}', [ContractUnitController::class, 'indexByContract'])->middleware('permission:units.view');
        });

        // NOTE: locations moved under /inventory/contracts/locations (GET/POST) above.
    });

    // ==========================================
    // SALES DEPARTMENT ROUTES
    // ==========================================
    Route::prefix('sales')->middleware(['auth:sanctum', 'role:sales|sales_leader|admin'])->group(function () {

        // Dashboard
        Route::get('dashboard', [SalesDashboardController::class, 'index'])->middleware('permission:sales.dashboard.view');

        // Projects
        Route::get('projects', [SalesProjectController::class, 'index'])->middleware('permission:sales.projects.view');
        Route::get('projects/{contractId}', [SalesProjectController::class, 'show'])->middleware('permission:sales.projects.view');
        Route::get('projects/{contractId}/units', [SalesProjectController::class, 'units'])->middleware('permission:sales.projects.view');

        // Reservation context
        Route::get('units/{unitId}/reservation-context', [SalesReservationController::class, 'context'])->middleware('permission:sales.reservations.create');

        // Reservations
        Route::post('reservations', [SalesReservationController::class, 'store'])->middleware('permission:sales.reservations.create');
        Route::get('reservations', [SalesReservationController::class, 'index'])->middleware('permission:sales.reservations.view');
        Route::post('reservations/{id}/confirm', [SalesReservationController::class, 'confirm'])->middleware('permission:sales.reservations.confirm');
        Route::post('reservations/{id}/cancel', [SalesReservationController::class, 'cancel'])->middleware('permission:sales.reservations.cancel');
        Route::post('reservations/{id}/actions', [SalesReservationController::class, 'storeAction'])->middleware('permission:sales.reservations.view');
        Route::get('reservations/{id}/voucher', [SalesReservationController::class, 'downloadVoucher'])->middleware('permission:sales.reservations.view');

        // My targets
        Route::get('targets/my', [SalesTargetController::class, 'my'])->middleware('permission:sales.targets.view');
        Route::patch('targets/{id}', [SalesTargetController::class, 'update'])->middleware('permission:sales.targets.update');

        // My attendance
        Route::get('attendance/my', [SalesAttendanceController::class, 'my'])->middleware('permission:sales.attendance.view');

        // My assignments
        Route::get('assignments/my', [SalesProjectController::class, 'getMyAssignments'])->middleware('permission:sales.projects.view');

        // Team management (leader only)
        Route::middleware('permission:sales.team.manage')->group(function () {
            Route::get('team/projects', [SalesProjectController::class, 'teamProjects']);
            Route::get('team/members', [SalesProjectController::class, 'teamMembers']);
            Route::patch('projects/{contractId}/emergency-contacts', [SalesProjectController::class, 'updateEmergencyContacts']);

            Route::post('targets', [SalesTargetController::class, 'store']);

            Route::get('attendance/team', [SalesAttendanceController::class, 'team']);
            Route::post('attendance/schedules', [SalesAttendanceController::class, 'store']);

            Route::get('tasks/projects', [MarketingTaskController::class, 'projects'])->middleware('permission:sales.tasks.manage');
            Route::get('tasks/projects/{contractId}', [MarketingTaskController::class, 'showProject'])->middleware('permission:sales.tasks.manage');
            Route::post('marketing-tasks', [MarketingTaskController::class, 'store'])->middleware('permission:sales.tasks.manage');
            Route::patch('marketing-tasks/{id}', [MarketingTaskController::class, 'update'])->middleware('permission:sales.tasks.manage');
        });

        // Waiting List Routes
        Route::prefix('waiting-list')->group(function () {
            Route::get('/', [WaitingListController::class, 'index'])->middleware('permission:sales.waiting_list.create');
            Route::get('/unit/{unitId}', [WaitingListController::class, 'getByUnit'])->middleware('permission:sales.waiting_list.create');
            Route::post('/', [WaitingListController::class, 'store'])->middleware('permission:sales.waiting_list.create');
            Route::post('/{id}/convert', [WaitingListController::class, 'convert'])->middleware('permission:sales.waiting_list.convert');
            Route::delete('/{id}', [WaitingListController::class, 'cancel'])->middleware('permission:sales.waiting_list.create');
        });

        // Negotiation Approval Routes (Manager only)
        Route::prefix('negotiations')->middleware('permission:sales.negotiation.approve')->group(function () {
            Route::get('/pending', [NegotiationApprovalController::class, 'index']);
            Route::post('/{id}/approve', [NegotiationApprovalController::class, 'approve']);
            Route::post('/{id}/reject', [NegotiationApprovalController::class, 'reject']);
        });

        // Payment Plan Routes (Off-plan projects)
        Route::middleware('permission:sales.payment-plan.manage')->group(function () {
            Route::get('reservations/{id}/payment-plan', [PaymentPlanController::class, 'show']);
            Route::post('reservations/{id}/payment-plan', [PaymentPlanController::class, 'store']);
            Route::put('payment-installments/{id}', [PaymentPlanController::class, 'update']);
            Route::delete('payment-installments/{id}', [PaymentPlanController::class, 'destroy']);
        });
    });


            // Create an admin prefix group with admin middleware
        Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

            Route::prefix('employees')->group(function () {
                Route::get('/roles', [RegisterController::class, 'list_roles'])->middleware('permission:employees.manage');
                Route::post('/add_employee', [RegisterController::class, 'add_employee'])->middleware('permission:employees.manage');
                    Route::get('/list_employees', [RegisterController::class, 'list_employees'])->middleware('permission:employees.manage');
                    Route::get('/show_employee/{id}', [RegisterController::class, 'show_employee'])->middleware('permission:employees.manage');
                    Route::put('/update_employee/{id}', [RegisterController::class, 'update_employee'])->middleware('permission:employees.manage');
                    Route::delete('/delete_employee/{id}', [RegisterController::class, 'delete_employee'])->middleware('permission:employees.manage');
                    Route::patch('/restore/{id}', [RegisterController::class, 'restore_employee'])->middleware('permission:employees.manage');
            });

            Route::prefix('contracts')->group(function () {
                Route::get('/adminIndex', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
                Route::patch('adminUpdateStatus/{id}', [ContractController::class, 'adminUpdateStatus'])->middleware('permission:contracts.approve');
            });

            // ==========================================
            // ADMIN NOTIFICATIONS API
            // ==========================================
            Route::prefix('notifications')->group(function () {
                // Get admin's own notifications
                Route::get('/', [NotificationController::class, 'getAdminNotifications'])->middleware('permission:notifications.view');
                Route::post('/send-to-user', [NotificationController::class, 'sendToUser'])->middleware('permission:notifications.manage');
                Route::post('/send-public', [NotificationController::class, 'sendPublic'])->middleware('permission:notifications.manage');
                // Get all notifications of specific user
                Route::get('/user/{userId}', [NotificationController::class, 'getUserNotificationsByAdmin'])->middleware('permission:notifications.manage');
                // Get all public notifications
                Route::get('/public', [NotificationController::class, 'getAllPublicNotifications'])->middleware('permission:notifications.manage');
            });

            // ==========================================
            // ADMIN SALES API - Project Assignments
            // ==========================================
            Route::prefix('sales')->group(function () {
                Route::post('project-assignments', [SalesProjectController::class, 'assignProject'])->middleware('permission:sales.team.manage');
            });
        });

    // ==========================================
    // EXCLUSIVE PROJECT ROUTES (All except HR)
    // ==========================================
    Route::prefix('exclusive-projects')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [ExclusiveProjectController::class, 'index']);
        Route::get('/{id}', [ExclusiveProjectController::class, 'show']);
        Route::post('/', [ExclusiveProjectController::class, 'store'])->middleware('permission:exclusive_projects.request');
        Route::post('/{id}/approve', [ExclusiveProjectController::class, 'approve'])->middleware('permission:exclusive_projects.approve');
        Route::post('/{id}/reject', [ExclusiveProjectController::class, 'reject'])->middleware('permission:exclusive_projects.approve');
        Route::put('/{id}/contract', [ExclusiveProjectController::class, 'completeContract'])->middleware('permission:exclusive_projects.contract.complete');
        Route::get('/{id}/export', [ExclusiveProjectController::class, 'exportContract'])->middleware('permission:exclusive_projects.contract.export');
    });

    // ==========================================
    // HR DEPARTMENT ROUTES
    // ==========================================
    Route::prefix('hr')->middleware(['auth:sanctum', 'role:hr|admin'])->group(function () {

        // Dashboard
        Route::get('dashboard', [HrDashboardController::class, 'index'])->middleware('permission:hr.dashboard.view');
        Route::post('dashboard/refresh', [HrDashboardController::class, 'refresh'])->middleware('permission:hr.dashboard.view');

        // Teams
        Route::get('teams', [HrTeamController::class, 'index'])->middleware('permission:hr.teams.manage');
        Route::post('teams', [HrTeamController::class, 'store'])->middleware('permission:hr.teams.manage');
        Route::get('teams/{id}', [HrTeamController::class, 'show'])->middleware('permission:hr.teams.manage');
        Route::put('teams/{id}', [HrTeamController::class, 'update'])->middleware('permission:hr.teams.manage');
        Route::delete('teams/{id}', [HrTeamController::class, 'destroy'])->middleware('permission:hr.teams.manage');
        Route::post('teams/{id}/members', [HrTeamController::class, 'assignMember'])->middleware('permission:hr.teams.manage');
        Route::delete('teams/{id}/members/{userId}', [HrTeamController::class, 'removeMember'])->middleware('permission:hr.teams.manage');

        // Marketer Performance
        Route::get('marketers/performance', [MarketerPerformanceController::class, 'index'])->middleware('permission:hr.performance.view');
        Route::get('marketers/{id}/performance', [MarketerPerformanceController::class, 'show'])->middleware('permission:hr.performance.view');

        // Users
        Route::get('users', [HrUserController::class, 'index'])->middleware('permission:hr.employees.manage');
        Route::post('users', [HrUserController::class, 'store'])->middleware('permission:hr.employees.manage');
        Route::get('users/{id}', [HrUserController::class, 'show'])->middleware('permission:hr.employees.manage');
        Route::put('users/{id}', [HrUserController::class, 'update'])->middleware('permission:hr.employees.manage');
        Route::patch('users/{id}/status', [HrUserController::class, 'toggleStatus'])->middleware('permission:hr.employees.manage');
        Route::delete('users/{id}', [HrUserController::class, 'destroy'])->middleware('permission:hr.employees.manage');
        Route::post('users/{id}/files', [HrUserController::class, 'uploadFiles'])->middleware('permission:hr.employees.manage');

        // Warnings
        Route::get('users/{id}/warnings', [EmployeeWarningController::class, 'index'])->middleware('permission:hr.warnings.manage');
        Route::post('users/{id}/warnings', [EmployeeWarningController::class, 'store'])->middleware('permission:hr.warnings.manage');
        Route::delete('warnings/{id}', [EmployeeWarningController::class, 'destroy'])->middleware('permission:hr.warnings.manage');

        // Contracts
        Route::get('users/{id}/contracts', [EmployeeContractController::class, 'index'])->middleware('permission:hr.contracts.manage');
        Route::post('users/{id}/contracts', [EmployeeContractController::class, 'store'])->middleware('permission:hr.contracts.manage');
        Route::get('contracts/{id}', [EmployeeContractController::class, 'show'])->middleware('permission:hr.contracts.manage');
        Route::put('contracts/{id}', [EmployeeContractController::class, 'update'])->middleware('permission:hr.contracts.manage');
        Route::post('contracts/{id}/pdf', [EmployeeContractController::class, 'generatePdf'])->middleware('permission:hr.contracts.manage');
        Route::get('contracts/{id}/pdf', [EmployeeContractController::class, 'downloadPdf'])->middleware('permission:hr.contracts.manage');
        Route::post('contracts/{id}/activate', [EmployeeContractController::class, 'activate'])->middleware('permission:hr.contracts.manage');
        Route::post('contracts/{id}/terminate', [EmployeeContractController::class, 'terminate'])->middleware('permission:hr.contracts.manage');

        // Reports
        Route::get('reports/team-performance', [HrReportController::class, 'teamPerformance'])->middleware('permission:hr.reports.view');
        Route::get('reports/marketer-performance', [HrReportController::class, 'marketerPerformance'])->middleware('permission:hr.reports.view');
        Route::get('reports/employee-count', [HrReportController::class, 'employeeCount'])->middleware('permission:hr.reports.view');
        Route::get('reports/expiring-contracts', [HrReportController::class, 'expiringContracts'])->middleware('permission:hr.reports.view');
    });

    // ==========================================
    // MARKETING DEPARTMENT ROUTES
    // ==========================================
    Route::prefix('marketing')->middleware(['auth:sanctum', 'role:marketing|admin'])->group(function () {

        // Dashboard
        Route::get('dashboard', [MarketingDashboardController::class, 'index'])->middleware('permission:marketing.dashboard.view');

        // Projects
        Route::get('projects', [MarketingProjectController::class, 'index'])->middleware('permission:marketing.projects.view');
        Route::get('projects/{contractId}', [MarketingProjectController::class, 'show'])->middleware('permission:marketing.projects.view');
        Route::post('projects/calculate-budget', [MarketingProjectController::class, 'calculateBudget'])->middleware('permission:marketing.budgets.manage');

        // Developer Plans
        Route::get('developer-plans/{contractId}', [DeveloperMarketingPlanController::class, 'show'])->middleware('permission:marketing.plans.create');
        Route::post('developer-plans', [DeveloperMarketingPlanController::class, 'store'])->middleware('permission:marketing.plans.create');

        // Employee Plans
        Route::get('employee-plans/project/{projectId}', [EmployeeMarketingPlanController::class, 'index'])->middleware('permission:marketing.plans.create');
        Route::get('employee-plans/{planId}', [EmployeeMarketingPlanController::class, 'show'])->middleware('permission:marketing.plans.create');
        Route::post('employee-plans', [EmployeeMarketingPlanController::class, 'store'])->middleware('permission:marketing.plans.create');
        Route::post('employee-plans/auto-generate', [EmployeeMarketingPlanController::class, 'autoGenerate'])->middleware('permission:marketing.plans.create');

        // Expected Sales - Add POST method and GET without projectId first (before parameterized route)
        Route::post('expected-sales', [ExpectedSalesController::class, 'store'])->middleware('permission:marketing.budgets.manage');
        Route::get('expected-sales', [ExpectedSalesController::class, 'index'])->middleware('permission:marketing.budgets.manage');
        Route::get('expected-sales/{projectId}', [ExpectedSalesController::class, 'calculate'])->middleware('permission:marketing.budgets.manage');
        Route::put('settings/conversion-rate', [ExpectedSalesController::class, 'updateConversionRate'])->middleware('permission:marketing.budgets.manage');

        // Tasks
        Route::get('tasks', [MarketingModuleTaskController::class, 'index'])->middleware('permission:marketing.tasks.view');
        Route::post('tasks', [MarketingModuleTaskController::class, 'store'])->middleware('permission:marketing.tasks.confirm');
        Route::put('tasks/{taskId}', [MarketingModuleTaskController::class, 'update'])->middleware('permission:marketing.tasks.confirm');
        Route::patch('tasks/{taskId}/status', [MarketingModuleTaskController::class, 'updateStatus'])->middleware('permission:marketing.tasks.confirm');

        // Team Management
        Route::get('teams', [TeamManagementController::class, 'index'])->middleware('permission:marketing.teams.view');
        Route::post('teams/assign', [TeamManagementController::class, 'assignCampaign'])->middleware('permission:marketing.teams.manage');
        Route::post('projects/{projectId}/team', [TeamManagementController::class, 'assignTeam'])->middleware('permission:marketing.projects.view');
        Route::get('projects/{projectId}/team', [TeamManagementController::class, 'getTeam'])->middleware('permission:marketing.projects.view');
        Route::get('projects/{projectId}/recommend-employee', [TeamManagementController::class, 'recommendEmployee'])->middleware('permission:marketing.projects.view');

        // Route aliases for backward compatibility with Postman collection
        Route::post('plans/developer', [DeveloperMarketingPlanController::class, 'store'])->middleware('permission:marketing.plans.create');
        Route::get('plans/developer/{contractId}', [DeveloperMarketingPlanController::class, 'show'])->middleware('permission:marketing.plans.create');
        Route::post('plans/employee', [EmployeeMarketingPlanController::class, 'store'])->middleware('permission:marketing.plans.create');
        Route::get('plans/employee', [EmployeeMarketingPlanController::class, 'index'])->middleware('permission:marketing.plans.create');
        Route::get('plans/employee/{planId}', [EmployeeMarketingPlanController::class, 'show'])->middleware('permission:marketing.plans.create');

        // Leads
        Route::get('leads', [LeadController::class, 'index'])->middleware('permission:marketing.projects.view');
        Route::post('leads', [LeadController::class, 'store'])->middleware('permission:marketing.projects.view');
        Route::put('leads/{leadId}', [LeadController::class, 'update'])->middleware('permission:marketing.projects.view');
        Route::post('leads/{leadId}/convert', [LeadController::class, 'convert'])->middleware('permission:marketing.projects.view');
        Route::post('leads/{leadId}/assign', [LeadController::class, 'assign'])->middleware('permission:marketing.projects.view');

        // Reports
        Route::get('reports/project/{projectId}', [MarketingReportController::class, 'projectPerformance'])->middleware('permission:marketing.reports.view');
        Route::get('reports/budget', [MarketingReportController::class, 'budgetReport'])->middleware('permission:marketing.reports.view');
        Route::get('reports/expected-bookings', [MarketingReportController::class, 'expectedBookingsReport'])->middleware('permission:marketing.reports.view');
        Route::get('reports/employee/{userId}', [MarketingReportController::class, 'employeePerformance'])->middleware('permission:marketing.reports.view');
        Route::get('reports/export/{planId}', [MarketingReportController::class, 'exportPlan'])->middleware('permission:marketing.reports.view');

        // Settings
        Route::get('settings', [MarketingSettingsController::class, 'index'])->middleware('permission:marketing.budgets.manage');
        Route::put('settings/{key}', [MarketingSettingsController::class, 'update'])->middleware('permission:marketing.budgets.manage');
    });

    // ==========================================
    // CREDIT DEPARTMENT ROUTES
    // ==========================================
    Route::prefix('credit')->middleware(['auth:sanctum', 'role:credit|admin'])->group(function () {

        // Dashboard
        Route::get('dashboard', [CreditDashboardController::class, 'index'])->middleware('permission:credit.dashboard.view');
        Route::post('dashboard/refresh', [CreditDashboardController::class, 'refresh'])->middleware('permission:credit.dashboard.view');

        // Bookings
        Route::get('bookings/confirmed', [CreditBookingController::class, 'confirmed'])->middleware('permission:credit.bookings.view');
        Route::get('bookings/negotiation', [CreditBookingController::class, 'negotiation'])->middleware('permission:credit.bookings.view');
        Route::get('bookings/waiting', [CreditBookingController::class, 'waiting'])->middleware('permission:credit.bookings.view');
        Route::get('bookings/{id}', [CreditBookingController::class, 'show'])->middleware('permission:credit.bookings.view');

        // Financing Tracker
        Route::post('bookings/{id}/financing', [CreditFinancingController::class, 'initialize'])->middleware('permission:credit.financing.manage');
        Route::get('bookings/{id}/financing', [CreditFinancingController::class, 'show'])->middleware('permission:credit.bookings.view');
        Route::patch('financing/{id}/stage/{stage}', [CreditFinancingController::class, 'completeStage'])->middleware('permission:credit.financing.manage');
        Route::post('financing/{id}/reject', [CreditFinancingController::class, 'reject'])->middleware('permission:credit.financing.manage');

        // Title Transfer
        Route::post('bookings/{id}/title-transfer', [TitleTransferController::class, 'initialize'])->middleware('permission:credit.title_transfer.manage');
        Route::patch('title-transfer/{id}/schedule', [TitleTransferController::class, 'schedule'])->middleware('permission:credit.title_transfer.manage');
        Route::post('title-transfer/{id}/complete', [TitleTransferController::class, 'complete'])->middleware('permission:credit.title_transfer.manage');
        Route::get('title-transfers/pending', [TitleTransferController::class, 'pending'])->middleware('permission:credit.bookings.view');
        Route::get('sold-projects', [TitleTransferController::class, 'soldProjects'])->middleware('permission:credit.bookings.view');

        // Claim Files
        Route::post('bookings/{id}/claim-file', [ClaimFileController::class, 'generate'])->middleware('permission:credit.claim_files.generate');
        Route::get('claim-files/{id}', [ClaimFileController::class, 'show'])->middleware('permission:credit.claim_files.generate');
        Route::post('claim-files/{id}/pdf', [ClaimFileController::class, 'generatePdf'])->middleware('permission:credit.claim_files.generate');
        Route::get('claim-files/{id}/pdf', [ClaimFileController::class, 'download'])->middleware('permission:credit.claim_files.generate');
    });

    // ==========================================
    // ACCOUNTING DEPARTMENT ROUTES
    // ==========================================
    Route::prefix('accounting')->middleware(['auth:sanctum', 'role:accounting|admin'])->group(function () {

        // Dashboard (Tab 1)
        Route::get('dashboard', [AccountingDashboardController::class, 'index'])->middleware('permission:accounting.dashboard.view');

        // Notifications (Tab 2)
        Route::get('notifications', [AccountingNotificationController::class, 'index'])->middleware('permission:accounting.notifications.view');
        Route::post('notifications/{id}/read', [AccountingNotificationController::class, 'markAsRead'])->middleware('permission:accounting.notifications.view');
        Route::post('notifications/read-all', [AccountingNotificationController::class, 'markAllAsRead'])->middleware('permission:accounting.notifications.view');

        // Sold Units & Commissions (Tabs 3 & 4)
        Route::get('sold-units', [AccountingCommissionController::class, 'index'])->middleware('permission:accounting.sold-units.view');
        Route::get('sold-units/{id}', [AccountingCommissionController::class, 'show'])->middleware('permission:accounting.sold-units.view');
        Route::post('sold-units/{id}/commission', [AccountingCommissionController::class, 'createManual'])->middleware('permission:accounting.commissions.create');
        Route::put('commissions/{id}/distributions', [AccountingCommissionController::class, 'updateDistributions'])->middleware('permission:accounting.sold-units.manage');
        Route::post('commissions/{id}/distributions/{distId}/approve', [AccountingCommissionController::class, 'approveDistribution'])->middleware('permission:accounting.commissions.approve');
        Route::post('commissions/{id}/distributions/{distId}/reject', [AccountingCommissionController::class, 'rejectDistribution'])->middleware('permission:accounting.commissions.approve');
        Route::get('commissions/{id}/summary', [AccountingCommissionController::class, 'summary'])->middleware('permission:accounting.sold-units.view');
        Route::post('commissions/{id}/distributions/{distId}/confirm', [AccountingCommissionController::class, 'confirmPayment'])->middleware('permission:accounting.commissions.approve');

        // Deposits (Tab 5)
        Route::get('deposits/pending', [AccountingDepositController::class, 'pending'])->middleware('permission:accounting.deposits.view');
        Route::post('deposits/{id}/confirm', [AccountingDepositController::class, 'confirm'])->middleware('permission:accounting.deposits.manage');
        Route::get('deposits/follow-up', [AccountingDepositController::class, 'followUp'])->middleware('permission:accounting.deposits.view');
        Route::post('deposits/{id}/refund', [AccountingDepositController::class, 'refund'])->middleware('permission:accounting.deposits.manage');
        Route::post('deposits/claim-file/{reservationId}', [AccountingDepositController::class, 'generateClaimFile'])->middleware('permission:accounting.deposits.view');

        // Salaries (Tab 6)
        Route::get('salaries', [AccountingSalaryController::class, 'index'])->middleware('permission:accounting.salaries.view');
        Route::get('salaries/{userId}', [AccountingSalaryController::class, 'show'])->middleware('permission:accounting.salaries.view');
        Route::post('salaries/{userId}/distribute', [AccountingSalaryController::class, 'createDistribution'])->middleware('permission:accounting.salaries.distribute');
        Route::post('salaries/distributions/{distributionId}/approve', [AccountingSalaryController::class, 'approveDistribution'])->middleware('permission:accounting.salaries.distribute');
        Route::post('salaries/distributions/{distributionId}/paid', [AccountingSalaryController::class, 'markAsPaid'])->middleware('permission:accounting.salaries.distribute');

        // Legacy Down Payment Confirmations (keep for backward compatibility)
        Route::get('pending-confirmations', [AccountingConfirmationController::class, 'index'])->middleware('permission:accounting.down_payment.confirm');
        Route::post('confirm/{reservationId}', [AccountingConfirmationController::class, 'confirm'])->middleware('permission:accounting.down_payment.confirm');
        Route::get('confirmations/history', [AccountingConfirmationController::class, 'history'])->middleware('permission:accounting.down_payment.confirm');
    });

    // ==========================================
    // COMMISSION AND SALES ANALYTICS ROUTES
    // ==========================================
    Route::prefix('sales')->middleware(['auth:sanctum'])->group(function () {
        // Analytics Dashboard
        Route::get('analytics/dashboard', [\App\Http\Controllers\Api\SalesAnalyticsController::class, 'dashboard']);
        Route::get('analytics/sold-units', [\App\Http\Controllers\Api\SalesAnalyticsController::class, 'soldUnits']);
        Route::get('analytics/deposits/stats/project/{contractId}', [\App\Http\Controllers\Api\SalesAnalyticsController::class, 'depositStatsByProject']);
        Route::get('analytics/commissions/stats/employee/{userId}', [\App\Http\Controllers\Api\SalesAnalyticsController::class, 'commissionStatsByEmployee']);
        Route::get('analytics/commissions/monthly-report', [\App\Http\Controllers\Api\SalesAnalyticsController::class, 'monthlyCommissionReport']);

        // Commissions
        Route::prefix('commissions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\CommissionController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\CommissionController::class, 'store']);
            Route::get('/{commission}', [\App\Http\Controllers\Api\CommissionController::class, 'show']);
            Route::put('/{commission}/expenses', [\App\Http\Controllers\Api\CommissionController::class, 'updateExpenses']);
            Route::post('/{commission}/distributions', [\App\Http\Controllers\Api\CommissionController::class, 'addDistribution']);
            Route::post('/{commission}/distribute/lead-generation', [\App\Http\Controllers\Api\CommissionController::class, 'distributeLeadGeneration']);
            Route::post('/{commission}/distribute/persuasion', [\App\Http\Controllers\Api\CommissionController::class, 'distributePersuasion']);
            Route::post('/{commission}/distribute/closing', [\App\Http\Controllers\Api\CommissionController::class, 'distributeClosing']);
            Route::post('/{commission}/distribute/management', [\App\Http\Controllers\Api\CommissionController::class, 'distributeManagement']);
            Route::post('/{commission}/approve', [\App\Http\Controllers\Api\CommissionController::class, 'approve']);
            Route::post('/{commission}/mark-paid', [\App\Http\Controllers\Api\CommissionController::class, 'markAsPaid']);
            Route::get('/{commission}/summary', [\App\Http\Controllers\Api\CommissionController::class, 'summary']);

            // Distribution management
            Route::put('/distributions/{distribution}', [\App\Http\Controllers\Api\CommissionController::class, 'updateDistribution']);
            Route::delete('/distributions/{distribution}', [\App\Http\Controllers\Api\CommissionController::class, 'deleteDistribution']);
            Route::post('/distributions/{distribution}/approve', [\App\Http\Controllers\Api\CommissionController::class, 'approveDistribution']);
            Route::post('/distributions/{distribution}/reject', [\App\Http\Controllers\Api\CommissionController::class, 'rejectDistribution']);
        });

        // Deposits
        Route::prefix('deposits')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\DepositController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\DepositController::class, 'store']);
            Route::get('/follow-up', [\App\Http\Controllers\Api\DepositController::class, 'followUp']);
            Route::get('/{deposit}', [\App\Http\Controllers\Api\DepositController::class, 'show']);
            Route::put('/{deposit}', [\App\Http\Controllers\Api\DepositController::class, 'update']);
            Route::post('/{deposit}/confirm-receipt', [\App\Http\Controllers\Api\DepositController::class, 'confirmReceipt']);
            Route::post('/{deposit}/mark-received', [\App\Http\Controllers\Api\DepositController::class, 'markAsReceived']);
            Route::post('/{deposit}/refund', [\App\Http\Controllers\Api\DepositController::class, 'refund']);
            Route::post('/{deposit}/generate-claim', [\App\Http\Controllers\Api\DepositController::class, 'generateClaimFile']);
            Route::get('/{deposit}/can-refund', [\App\Http\Controllers\Api\DepositController::class, 'canRefund']);
            Route::delete('/{deposit}', [\App\Http\Controllers\Api\DepositController::class, 'destroy']);

            // Bulk operations
            Route::post('/bulk-confirm', [\App\Http\Controllers\Api\DepositController::class, 'bulkConfirm']);

            // By project/reservation
            Route::get('/stats/project/{contractId}', [\App\Http\Controllers\Api\DepositController::class, 'statsByProject']);
            Route::get('/by-reservation/{salesReservationId}', [\App\Http\Controllers\Api\DepositController::class, 'byReservation']);
            Route::get('/refundable/project/{contractId}', [\App\Http\Controllers\Api\DepositController::class, 'refundableDeposits']);
        });
    });

    Route::get('/storage/{path}', function ($path) {
        $filePath = storage_path('app/public/' . $path);

        if (!File::exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
    })->where('path', '.*');
    });


    Route::prefix('hr')->middleware(['auth:sanctum', 'hr'])->group(function () {


        Route::post('/add_employee', [RegisterController::class, 'add_employee']);
        Route::get('/list_employees', [RegisterController::class, 'list_employees']);
        Route::get('/show_employee/{id}', [RegisterController::class, 'show_employee']);
        Route::put('/update_employee/{id}', [RegisterController::class, 'update_employee']);
        Route::delete('/delete_employee/{id}', [RegisterController::class, 'delete_employee']);


        Route::prefix('teams')->group(function () {


            Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');
            Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');
            Route::get('/sales-average/{teamId}', [TeamController::class, 'salesAverage'])->whereNumber('teamId');
            Route::get('/getTeamsForContract/{contractId}', [ContractController::class, 'getTeamsForContract']);
        });

        // Dashboard route is defined in the first HR route group above (line ~357)

    });







    Route::prefix('teams')->middleware(['auth:sanctum'])->group(function () {

            Route::get('/index', [TeamController::class, 'index']);
            Route::get('/show/{id}', [TeamController::class, 'show']);
        });

