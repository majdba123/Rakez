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
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Sales\SalesProjectController;
use App\Http\Controllers\Sales\SalesReservationController;
use App\Http\Controllers\Sales\SalesTargetController;
use App\Http\Controllers\Sales\SalesAttendanceController;
use App\Http\Controllers\Sales\MarketingTaskController;
use App\Http\Controllers\Sales\WaitingListController;
use App\Http\Controllers\ExclusiveProjectController;
use App\Http\Middleware\CheckDynamicPermission;
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

        // Expected Sales
        Route::get('expected-sales/{projectId}', [ExpectedSalesController::class, 'calculate'])->middleware('permission:marketing.budgets.manage');
        Route::put('settings/conversion-rate', [ExpectedSalesController::class, 'updateConversionRate'])->middleware('permission:marketing.budgets.manage');

        // Tasks
        Route::get('tasks', [MarketingModuleTaskController::class, 'index'])->middleware('permission:marketing.tasks.view');
        Route::post('tasks', [MarketingModuleTaskController::class, 'store'])->middleware('permission:marketing.tasks.confirm');
        Route::put('tasks/{taskId}', [MarketingModuleTaskController::class, 'update'])->middleware('permission:marketing.tasks.confirm');
        Route::patch('tasks/{taskId}/status', [MarketingModuleTaskController::class, 'updateStatus'])->middleware('permission:marketing.tasks.confirm');

        // Team Management
        Route::post('projects/{projectId}/team', [TeamManagementController::class, 'assignTeam'])->middleware('permission:marketing.projects.view');
        Route::get('projects/{projectId}/team', [TeamManagementController::class, 'getTeam'])->middleware('permission:marketing.projects.view');
        Route::get('projects/{projectId}/recommend-employee', [TeamManagementController::class, 'recommendEmployee'])->middleware('permission:marketing.projects.view');

        // Leads
        Route::get('leads', [LeadController::class, 'index'])->middleware('permission:marketing.projects.view');
        Route::post('leads', [LeadController::class, 'store'])->middleware('permission:marketing.projects.view');
        Route::put('leads/{leadId}', [LeadController::class, 'update'])->middleware('permission:marketing.projects.view');

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

        Route::get('/dashboard', [DashboardController::class, 'hr']);

    });




        Route::prefix('inventory')->middleware(['auth:sanctum', 'inventory'])->group(function () {

            // Contracts
            Route::prefix('contracts')->group(function () {
                Route::get('/show/{id}', [ContractController::class, 'show']);
                Route::get('/admin-index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
            });

            // Second party data
            Route::prefix('second-party-data')->group(function () {
                Route::get('/show/{id}', [SecondPartyDataController::class, 'show'])->middleware('permission:second_party.view');
            });

            // Contract units
            Route::prefix('contracts/units')->group(function () {
                Route::get('/show/{contractId}', [ContractUnitController::class, 'indexByContract'])->middleware('permission:units.view');
            });

            // Team contract locations
            Route::get('/contracts/locations', [ContractController::class, 'locations'])->middleware('permission:contracts.view_all');
        });


                // Chat Routes
        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [ChatController::class, 'index']);
            Route::get('/conversations/{userId}', [ChatController::class, 'getOrCreateConversation']);
            Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
            Route::post('/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
            Route::patch('/conversations/{conversationId}/read', [ChatController::class, 'markAsRead']);
            Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
            Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
        });


    Route::prefix('teams')->middleware(['auth:sanctum'])->group(function () {

            Route::get('/index', [TeamController::class, 'index']);
            Route::get('/show/{id}', [TeamController::class, 'show']);
        });

