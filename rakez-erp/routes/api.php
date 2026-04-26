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
use App\Http\Controllers\Contract\ContractController;
use App\Http\Controllers\Contract\ContractInfoController;
use App\Http\Controllers\Contract\DeveloperController;
use App\Http\Controllers\Contract\SecondPartyDataController;
use App\Http\Controllers\Contract\ContractUnitController;
use App\Http\Controllers\Contract\BoardsDepartmentController;
use App\Http\Controllers\Contract\PhotographyDepartmentController;
use App\Http\Controllers\Contract\MontageDepartmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Dashboard\ProjectManagementDashboardController;
use App\Http\Controllers\AI\AIAssistantController;
use App\Http\Controllers\AI\AiV2Controller;
use App\Http\Controllers\AI\DocumentController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Sales\SalesExecutiveDashboardController;
use App\Http\Controllers\Sales\SalesProjectController;
use App\Http\Controllers\Sales\SalesReservationController;
use App\Http\Controllers\Sales\SalesTargetController;
use App\Http\Controllers\Sales\ExecutiveDirectorLineController;
use App\Http\Controllers\Sales\SalesAttendanceController;
use App\Http\Controllers\Sales\MarketingTaskController;
use App\Http\Controllers\Sales\SalesTeamController;
use App\Http\Controllers\Sales\WaitingListController;
use App\Http\Controllers\Sales\SalesInsightsController;
use App\Http\Controllers\Sales\SalesUnitSearchController;
use App\Http\Controllers\Api\SalesAnalyticsController;
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
use App\Http\Controllers\Marketing\MarketingEmployeeController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Accounting\AccountingCommissionController;
use App\Http\Controllers\Accounting\AccountingConfirmationController;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\AccountingDepositController;
use App\Http\Controllers\Accounting\AccountingNotificationController;
use App\Http\Controllers\Accounting\AccountingSalaryController;
use App\Http\Controllers\Credit\ClaimFileController;
use App\Http\Controllers\Credit\CreditBookingController;
use App\Http\Controllers\Credit\OrderMarketingDeveloperController;
use App\Http\Controllers\Credit\CreditDashboardController;
use App\Http\Controllers\Credit\CreditFinancingController;
use App\Http\Controllers\Credit\CreditNotificationController;
use App\Http\Controllers\Credit\TitleTransferController;
use App\Http\Controllers\AI\AiCallController;
use App\Http\Controllers\AI\AssistantChatController;
use App\Http\Controllers\AI\AssistantKnowledgeController;
use App\Http\Controllers\AI\TwilioWebhookController;
use App\Http\Controllers\Ads\AdsInsightsController;
use App\Http\Controllers\Ads\AdsLeadsController;
use App\Http\Controllers\Ads\AdsOutcomeController;
use App\Http\Controllers\Sales\NegotiationApprovalController;
use App\Http\Controllers\Sales\PaymentPlanController;
use App\Http\Controllers\HR\HrTeamController;
use App\Http\Controllers\HR\HrUserController;
use App\Http\Controllers\HR\ManagerEmployeeController;
use App\Http\Controllers\HR\ManagerTaskController;
use App\Http\Controllers\HR\HrDashboardController;
use App\Http\Controllers\HR\HrReportController;
use App\Http\Controllers\HR\EmployeeContractController;
use App\Http\Controllers\HR\EmployeeWarningController;
use App\Http\Controllers\HR\MarketerPerformanceController;
use App\Http\Controllers\HR\HrTargetController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\TaskMetaController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CsvImportController;
use App\Http\Controllers\Admin\DistrictController;

use Illuminate\Support\Facades\File;  // أضف هذا السطر في الأعلى

    Broadcast::routes(['middleware' => ['auth:sanctum']]);



    Route::get('/storage/{path}', function ($path) {
        // Prevent directory traversal attacks
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(403);
        }

        $filePath = storage_path('app/public/' . $path);
        $realBase = realpath(storage_path('app/public'));
        $realFile = realpath($filePath);

        // Ensure resolved path is still within the public storage directory
        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            abort(403);
        }

        if (!File::exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
        })->where('path', '.*');

    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');

    Route::get('/csrf-token', function () {
        return response()->json(['token' => csrf_token()]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

    Route::post('/logout', [LoginController::class, 'logout']);

    // Manager API - separate from hr; managers see only employees of their type
    Route::prefix('manager')->middleware('auth:sanctum')->group(function () {
        Route::get('employees', [ManagerEmployeeController::class, 'index']);
        Route::get('employees/{id}', [ManagerEmployeeController::class, 'show'])->whereNumber('id');
        Route::get('employees/{employeeId}/reviews', [ManagerEmployeeController::class, 'indexReviews'])->whereNumber('employeeId');
        Route::post('employees/{id}/reviews', [ManagerEmployeeController::class, 'storeReview'])->whereNumber('id');
        Route::get('employees/{employeeId}/reviews/{reviewId}', [ManagerEmployeeController::class, 'showReview'])->whereNumber(['employeeId', 'reviewId']);
        Route::put('employees/{employeeId}/reviews/{reviewId}', [ManagerEmployeeController::class, 'updateReview'])->whereNumber(['employeeId', 'reviewId']);
        Route::delete('employees/{employeeId}/reviews/{reviewId}', [ManagerEmployeeController::class, 'deleteReview'])->whereNumber(['employeeId', 'reviewId']);

        Route::get('tasks/statistics', [ManagerTaskController::class, 'statistics']);
        Route::get('tasks', [ManagerTaskController::class, 'index']);
        Route::get('tasks/{id}', [ManagerTaskController::class, 'show'])->whereNumber('id');
    });

    // Teams list for any authenticated user (must be before role-restricted project_management group)
       Route::get('/project_management/teams/index', [TeamController::class, 'index']);

    // Developers list (authorized via ContractPolicy in controller)
        Route::get('/developers', [DeveloperController::class, 'index']);
        Route::get('/developers/{developer_number}', [DeveloperController::class, 'show']);

        Route::prefix('ai')->middleware('throttle:ai-assistant')->group(function () {
            Route::post('/ask', [AIAssistantController::class, 'ask']);
            Route::post('/chat', [AIAssistantController::class, 'chat']);
            Route::get('/conversations', [AIAssistantController::class, 'conversations']);
            Route::delete('/conversations/{sessionId}', [AIAssistantController::class, 'deleteSession']);
            Route::get('/sections', [AIAssistantController::class, 'sections']);

            // Tool orchestrator (no /v2/ in URL — stable for frontend). Legacy /v2/* aliases kept for compatibility.
            Route::prefix('tools')->group(function () {
                Route::post('/chat', [AiV2Controller::class, 'chat']);
                Route::post('/stream', [AiV2Controller::class, 'stream']);
            });
            Route::prefix('v2')->group(function () {
                Route::post('/chat', [AiV2Controller::class, 'chat']);
                Route::post('/stream', [AiV2Controller::class, 'stream']);
            });

            // RAG Document Management
            Route::prefix('documents')->group(function () {
                Route::post('/', [DocumentController::class, 'store']);
                Route::get('/', [DocumentController::class, 'index']);
                Route::get('/{id}', [DocumentController::class, 'show']);
                Route::delete('/{id}', [DocumentController::class, 'destroy']);
                Route::post('/{id}/reindex', [DocumentController::class, 'reindex']);
                Route::post('/search', [DocumentController::class, 'search']);
            });
        });

    // Contract Routes - Protected routes (user contracts + sales for project-tracker)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/contracts/index', [ContractController::class, 'index']);
            Route::post('/contracts/store', [ContractController::class, 'store']);
            Route::get('/contracts/show/{id}', [ContractController::class, 'show']);
            Route::get('/contracts/show/{id}/pdf', [ContractController::class, 'showPdf'])->whereNumber('id');
            Route::get('/contracts/{id}/fill-data', [ContractController::class, 'fillData']);
            Route::get('/contracts/{id}/fill-data/pdf', [ContractController::class, 'fillDataPdf'])->whereNumber('id');
            Route::get('/contracts/{id}/summary-pdf-data', [ContractController::class, 'summaryPdfData']);
            Route::put('/contracts/update/{id}', [ContractController::class, 'update']);
            Route::delete('/contracts/{id}', [ContractController::class, 'destroy']);

            Route::post('/contracts/store/info/{id}', [ContractInfoController::class, 'store']);
            Route::put('/contracts/update/info/{id}', [ContractInfoController::class, 'update']);
            Route::get('/contracts/info/{contractId}/pdf', [ContractInfoController::class, 'downloadPdf'])->whereNumber('contractId');

            // Sales / project-tracker: view second-party-data and photography-department (authorized via ContractPolicy in controller)
            Route::get('/second-party-data/show/{id}', [SecondPartyDataController::class, 'show']);
            Route::get('/second-party-data/{contractId}/pdf', [SecondPartyDataController::class, 'downloadPdf'])->whereNumber('contractId');
            Route::get('/photography-department/show/{contractId}', [PhotographyDepartmentController::class, 'show']);


            Route::prefix('user/notifications')->group(function () {
                Route::get('/private', [NotificationController::class, 'getUserPrivateNotifications']);
                Route::get('/public', [NotificationController::class, 'getPublicNotifications']);
                Route::match(['patch', 'post'], '/mark-all-read', [NotificationController::class, 'userMarkAllAsRead']);
                // Accept numeric DB ids and client-only ids (e.g. local-*) — see NotificationController::userMarkAsRead
                Route::match(['patch', 'post', 'put'], '/{id}/read', [NotificationController::class, 'userMarkAsRead'])
                    ->where('id', '[A-Za-z0-9\-]+');
            });


            Route::prefix('cities')->group(function () {
                Route::get('/', [CityController::class, 'index']);
                Route::get('/{id}', [CityController::class, 'show'])->whereNumber('id');
            });

            // Districts (belongs to city; admin only)
            Route::prefix('districts')->group(function () {
                Route::get('/', [DistrictController::class, 'index']);
                Route::get('/{id}', [DistrictController::class, 'show'])->whereNumber('id');
            });


            // Shorthand /api/notifications -> returns private notifications for the authenticated user
            Route::get('/notifications', [NotificationController::class, 'getUserPrivateNotifications']);
            Route::match(['patch', 'post'], '/notifications/mark-all-read', [NotificationController::class, 'userMarkAllAsRead']);
            Route::match(['patch', 'post', 'put'], '/notifications/{id}/read', [NotificationController::class, 'userMarkAsRead'])
                ->where('id', '[A-Za-z0-9\-]+');
        });

        // Directory endpoints: PM, admin, accounting / accountant (still require second_party.view)
        Route::middleware(['auth:sanctum', 'role:project_management|admin|accounting|accountant'])->group(function () {
            Route::prefix('second-party-data')->group(function () {
                Route::get('/second-parties', [ContractInfoController::class, 'getAllSecondParties'])->middleware('permission:second_party.view');
                Route::get('/contracts-by-email', [ContractInfoController::class, 'getContractsBySecondPartyEmail'])->middleware('permission:second_party.view');
            });


            Route::get('order-marketing-developers', [OrderMarketingDeveloperController::class, 'index']);
            Route::post('order-marketing-developers', [OrderMarketingDeveloperController::class, 'store']);
            Route::get('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'show']);
            Route::put('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'update']);
            Route::delete('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'destroy']);


        });

        Route::middleware(['auth:sanctum', 'role:project_management|admin'])->group(function () {

            Route::get('/contracts/admin-index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
            Route::patch('contracts/update-status/{id}', [ContractController::class, 'projectManagementUpdateStatus'])->middleware('permission:contracts.approve');


            Route::prefix('second-party-data')->group(function () {
                // GET show/{id} is only on the auth-only group above (line ~127) so sales/sales_leader can use it; controller authorizes via ContractPolicy
                Route::post('store/{id}', [SecondPartyDataController::class, 'store'])->middleware('permission:second_party.edit');
                Route::put('update/{id}', [SecondPartyDataController::class, 'update'])->middleware('permission:second_party.edit');
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

            Route::prefix('montage-department')->group(function () {
                Route::get('show/{contractId}', [MontageDepartmentController::class, 'show'])->middleware('permission:departments.montage.view');
                Route::post('store/{contractId}', [MontageDepartmentController::class, 'store'])->middleware('permission:departments.montage.edit');
                Route::put('update/{contractId}', [MontageDepartmentController::class, 'update'])->middleware('permission:departments.montage.edit');
                Route::patch('approve/{contractId}', [MontageDepartmentController::class, 'approve']);
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

                            Route::get('sales-without-team', [TeamController::class, 'salesWithoutTeam']);
                            Route::get('members/{teamId}', [TeamController::class, 'members'])->whereNumber('teamId');
                            Route::post('members/{teamId}/', [TeamController::class, 'assignMember'])->whereNumber('teamId');
                            Route::delete('members/{teamId}/{userId}', [TeamController::class, 'removeMember'])->whereNumber(['teamId', 'userId']);

                            Route::get('/index/{contractId}', [ContractController::class, 'getTeamsForContract_HR']);
                            Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');

                            Route::post('/add/{contractId}', [ContractController::class, 'addTeamsToContract']);
                            Route::post('/remove/{contractId}', [ContractController::class, 'removeTeamsFromContract']);

                            Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');

                        });

                        Route::get('units/{unitId}/reservation-context', [SalesReservationController::class, 'context'])->middleware('permission:sales.reservations.create');

                        Route::prefix('reservations')->group(function () {
                            Route::post('/', [SalesReservationController::class, 'store'])->middleware('permission:sales.reservations.create');
                            Route::get('/', [SalesReservationController::class, 'index'])->middleware('permission:sales.reservations.view');
                            Route::get('/{id}', [SalesReservationController::class, 'show'])->whereNumber('id')->middleware('permission:sales.reservations.view');
                            Route::post('/{id}/confirm', [SalesReservationController::class, 'confirm'])->middleware('permission:sales.reservations.confirm');
                            Route::post('/{id}/cancel', [SalesReservationController::class, 'cancel'])->middleware('permission:sales.reservations.cancel');
                            Route::post('/{id}/actions', [SalesReservationController::class, 'storeAction'])->middleware('permission:sales.reservations.view');
                            Route::get('/{id}/voucher', [SalesReservationController::class, 'downloadVoucher'])->middleware('permission:sales.reservations.view');
                            Route::get('/{id}/voucher-data', [SalesReservationController::class, 'voucherData'])->middleware('permission:sales.reservations.view');
                        });

            });

        });


        Route::prefix('editor')->middleware(['auth:sanctum', 'role:editor|admin'])->group(function () {

            // Contracts - view all & individual contract
            Route::prefix('contracts')->group(function () {
                Route::get('/index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
                Route::get('/show/{id}', [ContractController::class, 'show']);
                Route::get('/show/{id}/pdf', [ContractController::class, 'showPdf'])->whereNumber('id');
            });

            Route::prefix('teams')->group(function () {

                Route::get('/', [MontageDepartmentController::class, 'team_index']);

            });

            // Second Party Data - view only
            Route::prefix('second-party-data')->group(function () {
                Route::get('show/{id}', [SecondPartyDataController::class, 'show'])->middleware('permission:second_party.view');
                Route::get('{contractId}/pdf', [SecondPartyDataController::class, 'downloadPdf'])->whereNumber('contractId')->middleware('permission:second_party.view');
            });

            // Contract Units - view only
            Route::prefix('contracts/units')->group(function () {
                Route::get('show/{contractId}', [ContractUnitController::class, 'indexByContract'])->middleware('permission:units.view');
            });

            // Developers - browse & detail
            Route::prefix('developers')->group(function () {
                Route::get('/', [DeveloperController::class, 'index'])->middleware('permission:contracts.view_all');
                Route::get('/{developer_number}', [DeveloperController::class, 'show'])->middleware('permission:contracts.view');
            });

            // Montage Department - قسم المونتاج
            Route::prefix('montage-department')->group(function () {
                Route::get('show/{contractId}', [MontageDepartmentController::class, 'show'])->middleware('permission:departments.montage.view');
                Route::post('store/{contractId}', [MontageDepartmentController::class, 'store'])->middleware('permission:departments.montage.edit');
                Route::put('update/{contractId}', [MontageDepartmentController::class, 'update'])->middleware('permission:departments.montage.edit');
                Route::patch('approve/{contractId}', [MontageDepartmentController::class, 'approve'])->middleware('permission:departments.montage.edit');
            });

            // Photography Department - قسم التصوير
            Route::prefix('photography-department')->group(function () {
                Route::get('show/{contractId}', [PhotographyDepartmentController::class, 'show'])->middleware('permission:departments.photography.view');
                Route::post('store/{contractId}', [PhotographyDepartmentController::class, 'store'])->middleware('permission:departments.photography.edit');
                Route::put('update/{contractId}', [PhotographyDepartmentController::class, 'update'])->middleware('permission:departments.photography.edit');
                Route::patch('approve/{contractId}', [PhotographyDepartmentController::class, 'approve'])->middleware('permission:departments.photography.edit');
            });

            // Boards Department - قسم اللوحات
            Route::prefix('boards-department')->group(function () {
                Route::get('show/{contractId}', [BoardsDepartmentController::class, 'show'])->middleware('permission:departments.boards.view');
                Route::post('store/{contractId}', [BoardsDepartmentController::class, 'store'])->middleware('permission:departments.boards.edit');
                Route::put('update/{contractId}', [BoardsDepartmentController::class, 'update'])->middleware('permission:departments.boards.edit');
            });

        });

        Route::prefix('sales')->middleware(['auth:sanctum', 'role:sales|sales_leader|admin'])->group(function () {

            // Dashboard
            Route::get('dashboard', [SalesDashboardController::class, 'index'])->middleware('permission:sales.dashboard.view');

            // Executive director: available units stock + summary by unit_type
            Route::get('executive/available-units', [SalesExecutiveDashboardController::class, 'availableUnits'])
                ->middleware(['sales_executive', 'permission:sales.dashboard.view']);

            // ExecutiveDirectorLine list (admin or sales+manager); same resource as executive-director-lines, different access
            Route::get('executive/targets', [ExecutiveDirectorLineController::class, 'executiveTargets']);
            Route::post('executive-director-lines/{id}/teams', [ExecutiveDirectorLineController::class, 'syncTeams'])->whereNumber('id');
            Route::get('team/index', [HrTeamController::class, 'index']);

            $executiveLineMiddleware = ['sales_executive'];
            // Standalone executive-director lines (line_type + value only; not linked to sales targets)
            Route::get('executive-director-lines', [ExecutiveDirectorLineController::class, 'index'])->middleware($executiveLineMiddleware);
            Route::post('executive-director-lines', [ExecutiveDirectorLineController::class, 'store'])->middleware($executiveLineMiddleware);
            // Assign line to one or many teams: admin or sales+manager; not restricted to sales_executive
            Route::get('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'show'])->whereNumber('id')->middleware($executiveLineMiddleware);
            Route::put('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'update'])->whereNumber('id')->middleware($executiveLineMiddleware);
            Route::delete('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'destroy'])->whereNumber('id')->middleware($executiveLineMiddleware);

            // Projects
            Route::get('projects', [SalesProjectController::class, 'index'])->middleware('permission:sales.projects.view');
            Route::get('projects/{contractId}', [SalesProjectController::class, 'show'])->middleware('permission:sales.projects.view');
            Route::get('projects/{contractId}/units', [SalesProjectController::class, 'units'])->middleware('permission:sales.projects.view');
            Route::get('units/{id}/pdf', [SalesProjectController::class, 'unitPdf'])->middleware('permission:sales.projects.view');
            Route::get('units/{unitId}/pdf-data', [SalesProjectController::class, 'unitPdfData'])->middleware('permission:sales.projects.view')->whereNumber('unitId');

            // Unit Search (cross-project)
            Route::get('units/search', [SalesUnitSearchController::class, 'search'])->middleware('permission:sales.projects.view');
            Route::get('units/filters', [SalesUnitSearchController::class, 'filters'])->middleware('permission:sales.projects.view');

            // Reservation context
            Route::get('units/{unitId}/reservation-context', [SalesReservationController::class, 'context'])->middleware('permission:sales.reservations.create');

            // Reservations
            Route::post('reservations', [SalesReservationController::class, 'store'])->middleware('permission:sales.reservations.create');
            Route::get('reservations', [SalesReservationController::class, 'index'])->middleware('permission:sales.reservations.view');
            Route::get('reservations/{id}', [SalesReservationController::class, 'show'])->middleware('permission:sales.reservations.view');
            Route::post('reservations/{id}/confirm', [SalesReservationController::class, 'confirm'])->middleware('permission:sales.reservations.confirm');
            Route::post('reservations/{id}/cancel', [SalesReservationController::class, 'cancel'])->middleware('permission:sales.reservations.cancel');
            Route::post('reservations/{id}/actions', [SalesReservationController::class, 'storeAction'])->middleware('permission:sales.reservations.view');
            Route::get('reservations/{id}/voucher', [SalesReservationController::class, 'downloadVoucher'])->middleware('permission:sales.reservations.view');
            Route::get('reservations/{id}/voucher-data', [SalesReservationController::class, 'voucherData'])->middleware('permission:sales.reservations.view');

            // My targets
            Route::get('targets/my', [SalesTargetController::class, 'my'])->middleware('permission:sales.targets.view');
            Route::get('targets/by-project/{contractId}', [SalesTargetController::class, 'byProject'])->middleware('permission:sales.targets.view');
            Route::patch('targets/{id}', [SalesTargetController::class, 'update'])->middleware('permission:sales.targets.update');

            // My attendance
            Route::get('attendance/my', [SalesAttendanceController::class, 'my'])->middleware('permission:sales.attendance.view');

            // My assignments (for sales leaders)
            Route::get('assignments/my', [SalesProjectController::class, 'getMyAssignments'])->middleware('permission:sales.team.manage');

            // Team management (leader only)
            Route::middleware('permission:sales.team.manage')->group(function () {
                Route::get('team/projects', [SalesProjectController::class, 'teamProjects']);
                Route::get('team/members', [SalesProjectController::class, 'teamMembers']);
                Route::get('team/recommendations', [SalesTeamController::class, 'recommendations']);
                Route::patch('team/members/{memberId}/rating', [SalesTeamController::class, 'rateMember']);
                Route::post('team/members/{memberId}/remove', [SalesTeamController::class, 'removeMember']);
                Route::patch('projects/{contractId}/emergency-contacts', [SalesProjectController::class, 'updateEmergencyContacts']);

                Route::post('targets', [SalesTargetController::class, 'store']);

                Route::get('attendance/team', [SalesAttendanceController::class, 'team']);
                Route::post('attendance/schedules', [SalesAttendanceController::class, 'store']);
                Route::get('attendance/project/{contractId}', [SalesAttendanceController::class, 'projectOverview']);
                Route::post('attendance/project/{contractId}/bulk', [SalesAttendanceController::class, 'bulkStore']);

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

            // Sales Insights Routes
            Route::get('sold-units', [SalesInsightsController::class, 'soldUnits'])->middleware('permission:sales.dashboard.view');
            Route::get('sold-units/{unitId}/commission-summary', [SalesInsightsController::class, 'soldUnitCommissionSummary'])->middleware('permission:sales.dashboard.view');
            Route::get('deposits/management', [SalesInsightsController::class, 'depositsManagement'])->middleware('permission:sales.dashboard.view');
            Route::get('deposits/follow-up', [SalesInsightsController::class, 'depositsFollowUp'])->middleware('permission:sales.dashboard.view');

            // Sales Analytics Routes
            Route::prefix('analytics')->group(function () {
                Route::get('dashboard', [SalesAnalyticsController::class, 'dashboard'])->middleware('permission:sales.dashboard.view');
                Route::get('sold-units', [SalesAnalyticsController::class, 'soldUnits'])->middleware('permission:sales.dashboard.view');
                Route::get('deposits/stats/project/{contractId}', [SalesAnalyticsController::class, 'depositStatsByProject'])->middleware('permission:sales.dashboard.view');
                Route::get('commissions/stats/employee/{userId}', [SalesAnalyticsController::class, 'commissionStatsByEmployee'])->middleware('permission:sales.dashboard.view');
                Route::get('commissions/monthly-report', [SalesAnalyticsController::class, 'monthlyCommissionReport'])->middleware('permission:sales.dashboard.view');
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

                // CSV: list all imports + types (admin). Upload endpoints return import_id; poll via GET …/csv/imports.
                Route::prefix('csv')->group(function () {
                    Route::get('types', [CsvImportController::class, 'types']);
                    Route::get('imports', [CsvImportController::class, 'index']);

                    Route::post('contracts/import_csv', [ContractController::class, 'import_contracts_csv']);

                    Route::post('contracts/import_info_csv/{contractId}', [ContractInfoController::class, 'import_csv'])->whereNumber('contractId');

                    Route::post('second-party-data/import_csv/{contractId}', [SecondPartyDataController::class, 'import_csv'])->whereNumber('contractId');

                    Route::post('teams/import_csv', [TeamController::class, 'import_csv']);

                    Route::post('employees/import_employees_csv', [RegisterController::class, 'import_employees_csv'])->middleware('permission:employees.manage');

                    Route::post('cities/import_csv', [CityController::class, 'import_csv']);

                    Route::post('districts/import_csv', [DistrictController::class, 'import_csv']);
                });

                // Cities reference data (admin only)
                Route::prefix('cities')->group(function () {
                    Route::get('/', [CityController::class, 'index']);
                    Route::post('/', [CityController::class, 'store']);
                    Route::get('/{id}', [CityController::class, 'show'])->whereNumber('id');
                    Route::put('/{id}', [CityController::class, 'update'])->whereNumber('id');
                    Route::patch('/{id}', [CityController::class, 'update'])->whereNumber('id');
                    Route::delete('/{id}', [CityController::class, 'destroy'])->whereNumber('id');
                });

                // Districts (belongs to city; admin only)
                Route::prefix('districts')->group(function () {
                    Route::get('/', [DistrictController::class, 'index']);
                    Route::post('/', [DistrictController::class, 'store']);
                    Route::get('/{id}', [DistrictController::class, 'show'])->whereNumber('id');
                    Route::put('/{id}', [DistrictController::class, 'update'])->whereNumber('id');
                    Route::patch('/{id}', [DistrictController::class, 'update'])->whereNumber('id');
                    Route::delete('/{id}', [DistrictController::class, 'destroy'])->whereNumber('id');
                });

                // Marketing developer orders (reuse Credit controller + OrderMarketingDeveloperService)
                Route::prefix('order-marketing-developers')->group(function () {
                    Route::get('/', [OrderMarketingDeveloperController::class, 'index']);
                    Route::get('/{id}', [OrderMarketingDeveloperController::class, 'show'])->whereNumber('id');
                    Route::patch('/{id}/status', [OrderMarketingDeveloperController::class, 'updateStatus'])->whereNumber('id');
                });
        });

    // ==========================================
    // EXCLUSIVE PROJECT ROUTES (any authenticated user can create a request; approve/contract remain restricted)
        Route::prefix('exclusive-projects')->middleware(['auth:sanctum'])->group(function () {
            Route::get('/', [ExclusiveProjectController::class, 'index']);
            Route::get('/{id}', [ExclusiveProjectController::class, 'show']);
            Route::post('/', [ExclusiveProjectController::class, 'store']);
            Route::post('/{id}/approve', [ExclusiveProjectController::class, 'approve'])->middleware('permission:exclusive_projects.approve');
            Route::post('/{id}/reject', [ExclusiveProjectController::class, 'reject'])->middleware('permission:exclusive_projects.approve');
            Route::put('/{id}/contract', [ExclusiveProjectController::class, 'completeContract'])->middleware('permission:exclusive_projects.contract.complete');
            Route::get('/{id}/export', [ExclusiveProjectController::class, 'exportContract'])->middleware('permission:exclusive_projects.contract.export');
        });

        // ==========================================
        // MARKETING DEPARTMENT ROUTES

        Route::prefix('marketing')->middleware(['auth:sanctum', 'role:marketing|admin'])->group(function () {

            // Dashboard
            Route::get('dashboard', [MarketingDashboardController::class, 'index'])->middleware('permission:marketing.dashboard.view');

            // Projects
            Route::get('projects', [MarketingProjectController::class, 'index'])->middleware('permission:marketing.projects.view');
            Route::get('projects/{id}', [MarketingProjectController::class, 'show'])->middleware('permission:marketing.projects.view');

            // Developer Plans
            Route::get('developer-plans/{contractId}', [DeveloperMarketingPlanController::class, 'show'])->middleware('permission:marketing.plans.create');
            Route::get('developer-plans/{contractId}/pdf', [DeveloperMarketingPlanController::class, 'downloadPdf'])->middleware('permission:marketing.plans.create')->whereNumber('contractId');
            Route::get('reports/developer-plan/{contractId}/pdf-data', [DeveloperMarketingPlanController::class, 'pdfData'])->middleware('permission:marketing.reports.view')->whereNumber('contractId');
            Route::post('developer-plans/calculate-budget', [DeveloperMarketingPlanController::class, 'calculateBudget'])->middleware('permission:marketing.plans.create');
            Route::post('developer-plans', [DeveloperMarketingPlanController::class, 'store'])->middleware('permission:marketing.plans.create');

            // Users list for marketing (e.g. employee-plans dropdown) – same as GET /hr/users
            Route::get('users', [HrUserController::class, 'index'])->middleware('permission:marketing.plans.create');

            // Employee Plans (GET with query ?project_id= supported; must be before employee-plans/{planId})
            Route::get('employee-plans', [EmployeeMarketingPlanController::class, 'index'])->middleware('permission:marketing.plans.create');
            Route::get('employee-plans/project/{projectId}', [EmployeeMarketingPlanController::class, 'index'])->middleware('permission:marketing.plans.create');
            Route::get('employee-plans/pdf-data', [EmployeeMarketingPlanController::class, 'pdfData'])->middleware('permission:marketing.reports.view');
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

            // Marketing Employees (view-only for marketing department)
            Route::get('employees', [MarketingEmployeeController::class, 'index'])->middleware('permission:marketing.teams.view');
            Route::get('employees/{id}', [MarketingEmployeeController::class, 'show'])->whereNumber('id')->middleware('permission:marketing.teams.view');

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
            Route::get('reports/distribution/project/{projectId}', [MarketingReportController::class, 'exportDistributionByProject'])->whereNumber('projectId')->middleware('permission:marketing.reports.view');
            Route::get('reports/distribution/{planId}', [MarketingReportController::class, 'exportDistribution'])->middleware('permission:marketing.reports.view');

            // Settings
            Route::get('settings', [MarketingSettingsController::class, 'index'])->middleware('permission:marketing.budgets.manage');
            Route::put('settings/{key}', [MarketingSettingsController::class, 'update'])->middleware('permission:marketing.budgets.manage');
        });

    });


    Route::prefix('hr')->middleware(['auth:sanctum', 'hr'])->group(function () {
            Route::post('/add_employee', [RegisterController::class, 'add_employee']);
            Route::get('/list_employees', [RegisterController::class, 'list_employees']);
            Route::get('/show_employee/{id}', [RegisterController::class, 'show_employee']);
            Route::put('/update_employee/{id}', [RegisterController::class, 'update_employee']);
            Route::delete('/delete_employee/{id}', [RegisterController::class, 'delete_employee']);

            Route::get('contracts/{id}/pdf-data', [EmployeeContractController::class, 'pdfData'])->whereNumber('id');

            Route::prefix('users')->group(function () {
                Route::get('/', [HrUserController::class, 'index']);
                Route::post('/', [HrUserController::class, 'store']);
                Route::get('/{id}/contracts', [EmployeeContractController::class, 'index'])->whereNumber('id');
                Route::post('/{id}/contracts', [EmployeeContractController::class, 'store'])->whereNumber('id');
                Route::post('/{id}/files', [HrUserController::class, 'uploadFiles'])->whereNumber('id');
                Route::patch('/{id}/status', [HrUserController::class, 'toggleStatus'])->whereNumber('id');
                Route::get('/{id}/warnings', [EmployeeWarningController::class, 'index'])->whereNumber('id');
                Route::post('/{id}/warnings', [EmployeeWarningController::class, 'store'])->whereNumber('id');
                Route::get('/{id}', [HrUserController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrUserController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrUserController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('teams')->group(function () {
                Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');
                Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');
                Route::get('/sales-average/{teamId}', [TeamController::class, 'salesAverage'])->whereNumber('teamId');
                Route::get('/getTeamsForContract/{contractId}', [ContractController::class, 'getTeamsForContract']);

                Route::get('/', [HrTeamController::class, 'index']);
                Route::post('/', [HrTeamController::class, 'store']);
                Route::get('/{id}/members', [HrTeamController::class, 'members'])->whereNumber('id');
                Route::post('/{id}/members', [HrTeamController::class, 'assignMember'])->whereNumber('id');
                Route::delete('/{id}/members/{userId}', [HrTeamController::class, 'removeMember'])->whereNumber(['id', 'userId']);
                Route::get('/{id}', [HrTeamController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrTeamController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrTeamController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('marketers')->group(function () {
                Route::get('/performance', [MarketerPerformanceController::class, 'index']);
                Route::get('/{id}/performance', [MarketerPerformanceController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('targets')->group(function () {
            //   Route::get('/', [HrTargetController::class, 'index']);
                Route::get('/statistics/{marketerId}', [HrTargetController::class, 'statistics'])->whereNumber('marketerId');
                Route::get('/marketers', [HrTargetController::class, 'marketers']);
                Route::get('/reservation-statistics/{marketerId}', [HrTargetController::class, 'reservationStatistics'])->whereNumber('marketerId');
            });

            Route::get('/dashboard', [DashboardController::class, 'hr']);

            // HR Dashboard KPIs
            Route::prefix('dashboard')->group(function () {
                Route::get('/', [HrDashboardController::class, 'index']);
                Route::post('/refresh', [HrDashboardController::class, 'refresh']);
            });

            // HR Reports
            Route::prefix('reports')->group(function () {
                Route::get('/team-performance', [HrReportController::class, 'teamPerformance']);
                Route::get('/marketer-performance', [HrReportController::class, 'marketerPerformance']);
                Route::get('/marketer-performance/pdf', [HrReportController::class, 'marketerPerformancePdf']);
                Route::get('/employee-count', [HrReportController::class, 'employeeCount']);
                Route::get('/expiring-contracts', [HrReportController::class, 'expiringContracts']);
                Route::get('/expiring-contracts/pdf', [HrReportController::class, 'expiringContractsPdf']);
                Route::get('/ended-contracts', [HrReportController::class, 'endedContracts']);
            });

    });




    Route::prefix('inventory')->middleware(['auth:sanctum', 'inventory'])->group(function () {

                // Contracts
                Route::prefix('contracts')->group(function () {
                    Route::get('/show/{id}', [ContractController::class, 'show']);
                    Route::get('/show/{id}/pdf', [ContractController::class, 'showPdf'])->whereNumber('id');
                    Route::get('/admin-index', [ContractController::class, 'adminIndex'])->middleware('permission:contracts.view_all');
                });

                // Second party data
                Route::prefix('second-party-data')->group(function () {
                    Route::get('/show/{id}', [SecondPartyDataController::class, 'show'])->middleware('permission:second_party.view');
                    Route::get('/{contractId}/pdf', [SecondPartyDataController::class, 'downloadPdf'])->whereNumber('contractId')->middleware('permission:second_party.view');
                });

                // Contract units
                Route::prefix('contracts/units')->group(function () {
                    Route::get('/show/{contractId}', [ContractUnitController::class, 'indexByContract'])->middleware('permission:units.view');
                });

                // Team contract locations & dashboard
                Route::get('/contracts/locations', [ContractController::class, 'locations'])->middleware('permission:contracts.view_all');
                Route::get('/contracts/agency-overview', [ContractController::class, 'inventoryAgencyOverview'])->middleware('permission:contracts.view_all');
                Route::get('/dashboard', [ContractController::class, 'inventoryDashboard'])->middleware('permission:contracts.view_all');

    });


            // My tasks (system-wide tasks assigned to current user) and task metadata
    Route::middleware('auth:sanctum')->group(function () {
                Route::get('/my-tasks', [MyTasksController::class, 'index']);
                Route::get('/requested-tasks', [MyTasksController::class, 'requestedTasks']);
                Route::patch('/my-tasks/{id}/status', [MyTasksController::class, 'updateStatus'])->whereNumber('id');
                Route::post('/tasks', [MyTasksController::class, 'store']);

                // Task sections and users by section (for assignment UI)
                Route::get('/tasks/sections', [TaskMetaController::class, 'sections']);
                Route::get('/tasks/sections/{section}/users', [TaskMetaController::class, 'usersBySection']);
    });

                // Chat Routes
    Route::prefix('chat')->middleware(['auth:sanctum'])->group(function () {
                Route::get('/conversations', [ChatController::class, 'index']);
                Route::get('/conversations/{userId}', [ChatController::class, 'getOrCreateConversation']);
                Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
                Route::post('/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
                Route::patch('/conversations/{conversationId}/read', [ChatController::class, 'markAsRead']);
                Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
                Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);

                Route::get('/list_user', [RegisterController::class, 'list_employees']);

    });


    Route::prefix('teams')->middleware(['auth:sanctum'])->group(function () {

                Route::get('/index', [TeamController::class, 'index']);
                Route::get('/show/{id}', [TeamController::class, 'show']);
    });

// ==========================================
    // ACCOUNTING DEPARTMENT ROUTES
    Route::prefix('accounting')->middleware(['auth:sanctum', 'role:accounting|admin'])->group(function () {

            Route::get('dashboard', [AccountingDashboardController::class, 'index'])->middleware('permission:accounting.dashboard.view');

            // Commission management
            Route::get('sold-units', [AccountingCommissionController::class, 'index'])->middleware('permission:accounting.sold-units.view');
            Route::get('commission-distribution-types', [AccountingCommissionController::class, 'distributionTypes'])->middleware('permission:accounting.sold-units.view');
            Route::get('marketers', [AccountingCommissionController::class, 'marketers'])->middleware('permission:accounting.sold-units.view');
            Route::get('sold-units/{id}', [AccountingCommissionController::class, 'show'])->middleware('permission:accounting.sold-units.view');
            Route::get('commissions/{id}/pdf-data', [AccountingCommissionController::class, 'commissionPdfData'])->middleware('permission:accounting.sold-units.view')->whereNumber('id');
            Route::get('commissions/released', [AccountingCommissionController::class, 'released'])->middleware('permission:accounting.sold-units.view');
            Route::post('sold-units/{id}/commission', [AccountingCommissionController::class, 'createManual'])->middleware('permission:accounting.sold-units.manage');
            Route::put('commissions/{id}/distributions', [AccountingCommissionController::class, 'updateDistributions'])->middleware('permission:accounting.sold-units.manage');
            Route::post('commissions/{id}/distributions/{distId}/approve', [AccountingCommissionController::class, 'approveDistribution'])->middleware('permission:accounting.commissions.approve');
            Route::post('commissions/{id}/distributions/{distId}/reject', [AccountingCommissionController::class, 'rejectDistribution'])->middleware('permission:accounting.commissions.approve');
            Route::get('commissions/{id}/summary', [AccountingCommissionController::class, 'summary'])->middleware('permission:accounting.sold-units.view');
            Route::post('commissions/{id}/distributions/{distId}/confirm', [AccountingCommissionController::class, 'confirmPayment'])->middleware('permission:accounting.sold-units.manage');

            // Deposit management
            Route::get('deposits/pending', [AccountingDepositController::class, 'pending'])->middleware('permission:accounting.deposits.view');
            Route::get('deposits/{id}/pdf-data', [AccountingDepositController::class, 'depositPdfData'])->middleware('permission:accounting.deposits.view')->whereNumber('id');
            Route::get('deposits/follow-up', [AccountingDepositController::class, 'followUp'])->middleware('permission:accounting.deposits.view');
            Route::post('deposits/{id}/confirm', [AccountingDepositController::class, 'confirm'])->middleware('permission:accounting.deposits.manage');
            Route::post('deposits/{id}/refund', [AccountingDepositController::class, 'refund'])->middleware('permission:accounting.deposits.manage');

            // Down payment confirmations
            Route::get('pending-confirmations', [AccountingConfirmationController::class, 'index'])->middleware('permission:accounting.deposits.view');
            Route::get('confirmations/history', [AccountingConfirmationController::class, 'history'])->middleware('permission:accounting.deposits.view');
            Route::post('confirmations/{id}/confirm', [AccountingConfirmationController::class, 'confirm'])->middleware('permission:accounting.deposits.manage');

            // Salary management
            Route::get('salaries', [AccountingSalaryController::class, 'index'])->middleware('permission:accounting.salaries.view');
            Route::get('salaries/{userId}', [AccountingSalaryController::class, 'show'])->middleware('permission:accounting.salaries.view');
            Route::post('salaries/{userId}/distribute', [AccountingSalaryController::class, 'createDistribution'])->middleware('permission:accounting.salaries.distribute');
            Route::post('salaries/distributions/{distributionId}/approve', [AccountingSalaryController::class, 'approveDistribution'])->middleware('permission:accounting.salaries.distribute');
            Route::post('salaries/distributions/{distributionId}/paid', [AccountingSalaryController::class, 'markAsPaid'])->middleware('permission:accounting.salaries.distribute');

            // Claim files: list + candidates + sold units + PDF by claim_file id
            Route::get('claim-files', [ClaimFileController::class, 'index'])->middleware('permission:accounting.claim_files.view');
            Route::get('claim-files/candidates', [ClaimFileController::class, 'candidates'])->middleware('permission:accounting.claim_files.view');
            Route::get('claim-files/sold-units', [ClaimFileController::class, 'soldUnitsByProject'])->middleware('permission:accounting.claim_files.view');
            Route::get('claim-files/{id}/pdf', [ClaimFileController::class, 'download'])->whereNumber('id')->middleware('permission:accounting.claim_files.manage');
            Route::post('claim-files/{id}/pdf', [ClaimFileController::class, 'generatePdf'])->whereNumber('id')->middleware('permission:accounting.claim_files.manage');
            Route::patch('claim-files/{id}', [ClaimFileController::class, 'updateClaimFileStatus'])->whereNumber('id')->middleware('permission:accounting.claim_files.manage');
            Route::post('claim-files/combined', [ClaimFileController::class, 'generateCombined'])->middleware('permission:accounting.claim_files.manage');

            // Notifications
            Route::get('notifications', [AccountingNotificationController::class, 'index'])->middleware('permission:accounting.dashboard.view');
            Route::post('notifications/read-all', [AccountingNotificationController::class, 'markAllAsRead'])->middleware('permission:accounting.dashboard.view');
            Route::post('notifications/{id}/read', [AccountingNotificationController::class, 'markAsRead'])->middleware('permission:accounting.dashboard.view');
    });

// ==========================================
    // CREDIT DEPARTMENT ROUTES
    Route::prefix('credit')->middleware(['auth:sanctum', 'role:credit|admin'])->group(function () {

            // Dashboard
            Route::get('dashboard', [CreditDashboardController::class, 'index'])->middleware('permission:credit.dashboard.view');
            Route::post('dashboard/refresh', [CreditDashboardController::class, 'refresh'])->middleware('permission:credit.dashboard.view');

            // Marketing developer orders (order_marketing_developers)
            Route::get('order-marketing-developers', [OrderMarketingDeveloperController::class, 'index'])->middleware('permission:credit.bookings.view');
            Route::post('order-marketing-developers', [OrderMarketingDeveloperController::class, 'store'])->middleware('permission:credit.bookings.manage');
            Route::get('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'show'])->middleware('permission:credit.bookings.view');
            Route::put('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'update'])->middleware('permission:credit.bookings.manage');
            Route::delete('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'destroy'])->middleware('permission:credit.bookings.manage');

            // Bookings
            Route::get('bookings', [CreditBookingController::class, 'index'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/confirmed', [CreditBookingController::class, 'confirmed'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/negotiation', [CreditBookingController::class, 'negotiation'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/waiting', [CreditBookingController::class, 'waiting'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/sold', [CreditBookingController::class, 'sold'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/cancelled', [CreditBookingController::class, 'cancelled'])->middleware('permission:credit.bookings.view');
            Route::get('bookings/{id}', [CreditBookingController::class, 'show'])->middleware('permission:credit.bookings.view');
            Route::patch('bookings/negotiation/{id}', [CreditBookingController::class, 'updateNegotiation'])->middleware('permission:credit.bookings.view');
            Route::post('bookings/{id}/cancel', [CreditBookingController::class, 'cancel'])->middleware('permission:credit.bookings.manage');

            // Financing
            Route::get('bookings/{id}/financing', [CreditFinancingController::class, 'show'])->middleware('permission:credit.financing.view');
            Route::post('bookings/{id}/financing', [CreditFinancingController::class, 'initialize'])->middleware('permission:credit.financing.manage');
            Route::post('bookings/{id}/financing/advance', [CreditFinancingController::class, 'advance'])->middleware('permission:credit.financing.manage');
            Route::patch('bookings/{bookingId}/financing/stage/{stage}', [CreditFinancingController::class, 'completeStage'])->middleware('permission:credit.financing.manage');
            Route::post('bookings/{bookingId}/financing/reject', [CreditFinancingController::class, 'reject'])->middleware('permission:credit.financing.manage');

            // Title Transfer
            Route::post('bookings/{id}/title-transfer', [TitleTransferController::class, 'initialize'])->middleware('permission:credit.title_transfer.manage');
            Route::get('title-transfers/pending', [TitleTransferController::class, 'pending'])->middleware('permission:credit.title_transfer.manage');
            Route::patch('title-transfer/{id}/schedule', [TitleTransferController::class, 'schedule'])->middleware('permission:credit.title_transfer.manage');
            Route::patch('title-transfer/{id}/unschedule', [TitleTransferController::class, 'unschedule'])->middleware('permission:credit.title_transfer.manage');
            Route::post('title-transfer/{id}/complete', [TitleTransferController::class, 'complete'])->middleware('permission:credit.title_transfer.manage');
            Route::get('sold-projects', [TitleTransferController::class, 'soldProjects'])->middleware('permission:credit.bookings.view');

            // Claim Files
          /*  Route::get('claim-files', [ClaimFileController::class, 'index'])->middleware('permission:credit.claim_files.view');
            Route::get('claim-files/candidates', [ClaimFileController::class, 'candidates'])->middleware('permission:credit.claim_files.manage');
            Route::post('claim-files/generate-bulk', [ClaimFileController::class, 'generateBulk'])->middleware('permission:credit.claim_files.manage');
            Route::post('claim-files/combined', [ClaimFileController::class, 'generateCombined'])->middleware('permission:credit.claim_files.manage');
            Route::get('claim-files/{id}', [ClaimFileController::class, 'show'])->middleware('permission:credit.claim_files.view');
            Route::get('claim-files/{id}/pdf', [ClaimFileController::class, 'download'])->middleware('permission:credit.claim_files.view');
            Route::post('claim-files/{id}/pdf', [ClaimFileController::class, 'generatePdf'])->middleware('permission:credit.claim_files.manage');
            Route::post('bookings/{id}/claim-file', [ClaimFileController::class, 'generate'])->middleware('permission:credit.claim_files.manage');
            */
            // Notifications
            Route::get('notifications', [CreditNotificationController::class, 'index'])->middleware('permission:credit.dashboard.view');
            Route::post('notifications/read-all', [CreditNotificationController::class, 'markAllAsRead'])->middleware('permission:credit.dashboard.view');
            Route::post('notifications/{id}/read', [CreditNotificationController::class, 'markAsRead'])->middleware('permission:credit.dashboard.view');
    });

// ==========================================
// AI CALLING ROUTES
    Route::prefix('ai/calls')->middleware(['auth:sanctum', 'role:admin|sales|sales_leader|marketing'])->group(function () {
        Route::get('/', [AiCallController::class, 'index'])->middleware('permission:ai-calls.manage');
        Route::get('/analytics', [AiCallController::class, 'analytics'])->middleware('permission:ai-calls.manage');
        Route::get('/scripts', [AiCallController::class, 'scripts'])->middleware('permission:ai-calls.manage');
        Route::post('/scripts', [AiCallController::class, 'storeScript'])->middleware('permission:ai-calls.manage');
        Route::put('/scripts/{id}', [AiCallController::class, 'updateScript'])->middleware('permission:ai-calls.manage');
        Route::delete('/scripts/{id}', [AiCallController::class, 'deleteScript'])->middleware('permission:ai-calls.manage');
        Route::post('/initiate', [AiCallController::class, 'initiate'])->middleware('permission:ai-calls.manage');
        Route::post('/bulk', [AiCallController::class, 'bulkInitiate'])->middleware('permission:ai-calls.manage');
        Route::get('/{id}', [AiCallController::class, 'show'])->middleware('permission:ai-calls.manage');
        Route::get('/{id}/transcript', [AiCallController::class, 'transcript'])->middleware('permission:ai-calls.manage');
        Route::post('/{id}/retry', [AiCallController::class, 'retry'])->middleware('permission:ai-calls.manage');
    });

    // ==========================================
    // ASSISTANT KNOWLEDGE BASE (Admin only)
    Route::prefix('ai/knowledge')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [AssistantKnowledgeController::class, 'index']);
        Route::post('/', [AssistantKnowledgeController::class, 'store']);
        Route::put('/{id}', [AssistantKnowledgeController::class, 'update']);
        Route::delete('/{id}', [AssistantKnowledgeController::class, 'destroy']);
    });

    Route::post('/ai/assistant/chat', [AssistantChatController::class, 'chat'])->middleware(['auth:sanctum']);

    // ==========================================
    Route::prefix('webhooks/twilio')->middleware([\App\Http\Middleware\ValidateTwilioSignature::class])->group(function () {
        Route::post('/voice/{callId}', [TwilioWebhookController::class, 'handleVoice']);
        Route::post('/gather/{callId}', [TwilioWebhookController::class, 'handleGather']);
        Route::post('/status/{callId}', [TwilioWebhookController::class, 'handleStatus']);
        Route::post('/fallback/{callId}', [TwilioWebhookController::class, 'handleFallback']);
    });

    // ==========================================
    Route::prefix('ads')->middleware(['auth:sanctum', 'role:admin|marketing'])->group(function () {
        Route::get('accounts', [AdsInsightsController::class, 'accounts'])->middleware('permission:marketing.ads.view');
        Route::get('campaigns', [AdsInsightsController::class, 'campaigns'])->middleware('permission:marketing.ads.view');
        Route::get('insights', [AdsInsightsController::class, 'insights'])->middleware('permission:marketing.ads.view');
        Route::get('leads', [AdsLeadsController::class, 'index'])->middleware('permission:marketing.ads.view');
        Route::get('leads/export', [AdsLeadsController::class, 'export'])->middleware('permission:marketing.ads.view');
        Route::post('leads/export-snap', [AdsLeadsController::class, 'exportSnap'])->middleware('permission:marketing.ads.view');
        Route::post('sync', [AdsInsightsController::class, 'triggerSync'])->middleware('permission:marketing.ads.manage');
        Route::post('outcomes', [AdsOutcomeController::class, 'store'])->middleware('permission:marketing.ads.manage');
        Route::get('outcomes/status', [AdsOutcomeController::class, 'status'])->middleware('permission:marketing.ads.view');
    });

    Route::prefix('sales')->middleware(['auth:sanctum', 'role:sales|sales_leader|admin'])->group(function () {

        // Negotiation Approvals
        Route::get('negotiations/pending', [NegotiationApprovalController::class, 'index'])->middleware('permission:sales.negotiation.approve');
        Route::post('negotiations/{id}/approve', [NegotiationApprovalController::class, 'approve'])->middleware('permission:sales.negotiation.approve');
        Route::post('negotiations/{id}/reject', [NegotiationApprovalController::class, 'reject'])->middleware('permission:sales.negotiation.approve');

        // Payment Plans
        Route::get('reservations/{id}/payment-plan', [PaymentPlanController::class, 'show'])->middleware('permission:sales.payment_plan.manage');
        Route::post('reservations/{id}/payment-plan', [PaymentPlanController::class, 'store'])->middleware('permission:sales.payment_plan.manage');
        Route::put('payment-installments/{id}', [PaymentPlanController::class, 'update'])->middleware('permission:sales.payment_plan.manage');
        Route::delete('payment-installments/{id}', [PaymentPlanController::class, 'destroy'])->middleware('permission:sales.payment_plan.manage');
    });
