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
use App\Http\Controllers\Sales\SalesUnitSearchAlertController;
use App\Http\Controllers\Api\SalesAnalyticsController;
use App\Http\Controllers\ExclusiveProjectController;
use App\Http\Middleware\CheckDynamicPermission;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamGroupController;
use App\Http\Controllers\TeamGroupLeaderController;
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
use App\Http\Controllers\Accounting\ProjectCommissionPreviewController;
use App\Http\Controllers\Accounting\ProjectCommissionSettingController;
use App\Http\Controllers\Accounting\UnitCommissionGenerationController;
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
use App\Http\Controllers\Ads\AdsAccountsController;
use App\Http\Controllers\Ads\AdsExportsController;
use App\Http\Controllers\Ads\AdsInsightsController;
use App\Http\Controllers\Ads\AdsLeadsController;
use App\Http\Controllers\Ads\AdsOpsController;
use App\Http\Controllers\Ads\AdsOutcomeController;
use App\Http\Controllers\Ads\AdsReportingController;
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

    Route::post('/login', [LoginController::class, 'login']);

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

    // Marketing developer orders — any authenticated user (no role/permission middleware)
        Route::get('/order-marketing-developers', [OrderMarketingDeveloperController::class, 'index']);
        Route::post('/order-marketing-developers', [OrderMarketingDeveloperController::class, 'store']);
        Route::get('/order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'show'])->whereNumber('id');
        Route::put('/order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'update'])->whereNumber('id');
        Route::delete('/order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'destroy'])->whereNumber('id');

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
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::prefix('second-party-data')->group(function () {
                Route::get('/second-parties', [ContractInfoController::class, 'getAllSecondParties']);
                Route::get('/contracts-by-email', [ContractInfoController::class, 'getContractsBySecondPartyEmail']);
            });

            Route::get('team_group/list', [TeamGroupController::class, 'index']);
            Route::get('team-group-leaders/list', [TeamGroupLeaderController::class, 'index']);


        });

        Route::middleware(['auth:sanctum'])->group(function () {

            Route::get('/contracts/admin-index', [ContractController::class, 'adminIndex']);
            Route::patch('contracts/update-status/{id}', [ContractController::class, 'projectManagementUpdateStatus']);


            Route::prefix('second-party-data')->group(function () {
                // GET show/{id} is only on the auth-only group above (line ~127) so sales/sales_leader can use it; controller authorizes via ContractPolicy
                Route::post('store/{id}', [SecondPartyDataController::class, 'store']);
                Route::put('update/{id}', [SecondPartyDataController::class, 'update']);
            });

            Route::prefix('contracts/units')->group(function () {
                Route::get('show/{contractId}', [ContractUnitController::class, 'indexByContract']);
                Route::post('upload-csv/{contractId}', [ContractUnitController::class, 'uploadCsvByContract']);
                Route::post('store/{contractId}', [ContractUnitController::class, 'store']);
                Route::put('update/{unitId}', [ContractUnitController::class, 'update']);
                Route::delete('delete/{unitId}', [ContractUnitController::class, 'destroy']);
            });

            Route::prefix('boards-department')->group(function () {
                Route::get('show/{contractId}', [BoardsDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [BoardsDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [BoardsDepartmentController::class, 'update']);
            });

            Route::prefix('montage-department')->group(function () {
                Route::get('show/{contractId}', [MontageDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [MontageDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [MontageDepartmentController::class, 'update']);
                Route::patch('approve/{contractId}', [MontageDepartmentController::class, 'approve']);
            });

            Route::prefix('photography-department')->group(function () {
                Route::get('show/{contractId}', [PhotographyDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [PhotographyDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [PhotographyDepartmentController::class, 'update']);
                Route::patch('approve/{contractId}', [PhotographyDepartmentController::class, 'approve']);

            });

            // لوحة تحكم إدارة المشاريع - Project Management Dashboard
            Route::prefix('project_management/dashboard')->group(function () {
                Route::get('/', [ProjectManagementDashboardController::class, 'index']);
                Route::get('/units-statistics', [ProjectManagementDashboardController::class, 'unitsStatistics']);
            });

            Route::prefix('project_management')->group(function () {

                        Route::prefix('teams')->group(function () {

                            Route::get('/index', [TeamController::class, 'index']);
                            Route::post('/store', [TeamController::class, 'store']);
                            Route::put('/update/{id}', [TeamController::class, 'update']);
                            Route::delete('/delete/{id}', [TeamController::class, 'destroy']);
                            Route::get('/show/{id}', [TeamController::class, 'show']);

                            Route::get('sales-without-team', [TeamController::class, 'salesWithoutTeam']);
                            Route::get('sales-leaders', [TeamController::class, 'salesLeadersIndex']);
                            Route::get('members/{teamId}', [TeamController::class, 'members'])->whereNumber('teamId');
                            Route::post('members/{teamId}/', [TeamController::class, 'assignMember'])->whereNumber('teamId');
                            Route::post('{teamId}/sales-leader', [TeamController::class, 'assignSalesLeader'])->whereNumber('teamId');
                            Route::delete('{teamId}/sales-leader/{userId}', [TeamController::class, 'removeSalesLeader'])->whereNumber(['teamId', 'userId']);
                            Route::delete('members/{teamId}/{userId}', [TeamController::class, 'removeMember'])->whereNumber(['teamId', 'userId']);

                            Route::get('/index/{contractId}', [ContractController::class, 'getTeamsForContract_HR']);
                            Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');

                            Route::post('/add/{contractId}', [ContractController::class, 'addTeamsToContract']);
                            Route::post('/remove/{contractId}', [ContractController::class, 'removeTeamsFromContract']);

                            Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');

                        });

                        // Team sub-groups (name + description), each belongs to one team; same CRUD as /hr/team-groups
                        Route::prefix('team-groups')->group(function () {
                            Route::get('{groupId}/members', [TeamController::class, 'membersOfTeamGroup'])->whereNumber('groupId');
                            Route::delete('{groupId}/members/{userId}', [TeamController::class, 'removeMemberFromTeamGroup'])->whereNumber(['groupId', 'userId']);
                            Route::get('/', [TeamGroupController::class, 'index']);
                            Route::post('/', [TeamGroupController::class, 'store']);
                            Route::post('/{id}/leader', [TeamGroupLeaderController::class, 'assign'])->whereNumber('id');
                            Route::delete('/{id}/leader', [TeamGroupLeaderController::class, 'remove'])->whereNumber('id');
                            Route::get('/{id}', [TeamGroupController::class, 'show'])->whereNumber('id');
                            Route::put('/{id}', [TeamGroupController::class, 'update'])->whereNumber('id');
                            Route::delete('/{id}', [TeamGroupController::class, 'destroy'])->whereNumber('id');
                        });

                        Route::get('team-group-leaders', [TeamGroupLeaderController::class, 'index']);

                        Route::get('units/{unitId}/reservation-context', [SalesReservationController::class, 'context']);

                        Route::prefix('reservations')->group(function () {
                            Route::post('/', [SalesReservationController::class, 'store']);
                            Route::get('/', [SalesReservationController::class, 'index']);
                            Route::get('/{id}', [SalesReservationController::class, 'show'])->whereNumber('id');
                            Route::post('/{id}/confirm', [SalesReservationController::class, 'confirm']);
                            Route::post('/{id}/cancel', [SalesReservationController::class, 'cancel']);
                            Route::post('/{id}/actions', [SalesReservationController::class, 'storeAction']);
                            Route::get('/{id}/voucher', [SalesReservationController::class, 'downloadVoucher']);
                            Route::get('/{id}/voucher-data', [SalesReservationController::class, 'voucherData']);
                        });

            });

        });


        Route::prefix('editor')->middleware(['auth:sanctum'])->group(function () {

            // Contracts - view all & individual contract
            Route::prefix('contracts')->group(function () {
                Route::get('/index', [ContractController::class, 'adminIndex']);
                Route::get('/show/{id}', [ContractController::class, 'show']);
                Route::get('/show/{id}/pdf', [ContractController::class, 'showPdf'])->whereNumber('id');
            });

            Route::prefix('teams')->group(function () {

                Route::get('/', [MontageDepartmentController::class, 'team_index']);

            });

            // Second Party Data - view only
            Route::prefix('second-party-data')->group(function () {
                Route::get('show/{id}', [SecondPartyDataController::class, 'show']);
                Route::get('{contractId}/pdf', [SecondPartyDataController::class, 'downloadPdf'])->whereNumber('contractId');
            });

            // Contract Units - view only
            Route::prefix('contracts/units')->group(function () {
                Route::get('show/{contractId}', [ContractUnitController::class, 'indexByContract']);
            });

            // Developers - browse & detail
            Route::prefix('developers')->group(function () {
                Route::get('/', [DeveloperController::class, 'index']);
                Route::get('/{developer_number}', [DeveloperController::class, 'show']);
            });

            // Montage Department - قسم المونتاج
            Route::prefix('montage-department')->group(function () {
                Route::get('show/{contractId}', [MontageDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [MontageDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [MontageDepartmentController::class, 'update']);
                Route::patch('approve/{contractId}', [MontageDepartmentController::class, 'approve']);
            });

            // Photography Department - قسم التصوير
            Route::prefix('photography-department')->group(function () {
                Route::get('show/{contractId}', [PhotographyDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [PhotographyDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [PhotographyDepartmentController::class, 'update']);
                Route::patch('approve/{contractId}', [PhotographyDepartmentController::class, 'approve']);
            });

            // Boards Department - قسم اللوحات
            Route::prefix('boards-department')->group(function () {
                Route::get('show/{contractId}', [BoardsDepartmentController::class, 'show']);
                Route::post('store/{contractId}', [BoardsDepartmentController::class, 'store']);
                Route::put('update/{contractId}', [BoardsDepartmentController::class, 'update']);
            });

        });

        Route::prefix('sales')->middleware(['auth:sanctum'])->group(function () {

            // Dashboard
            Route::get('dashboard', [SalesDashboardController::class, 'index']);

            // Executive director: available units stock + summary by unit_type
            Route::get('executive/available-units', [SalesExecutiveDashboardController::class, 'availableUnits'])
                ;

            // ExecutiveDirectorLine list (admin or sales+manager); same resource as executive-director-lines, different access
            Route::get('executive/targets', [ExecutiveDirectorLineController::class, 'executiveTargets']);
            Route::post('executive-director-lines/{id}/teams', [ExecutiveDirectorLineController::class, 'syncTeams'])->whereNumber('id');
            Route::get('team/index', [HrTeamController::class, 'index']);
            Route::get('team/led', [SalesTeamController::class, 'myLedTeam']);
            Route::get('team/groups', [SalesTeamController::class, 'myTeamGroups']);
            Route::get('team/group-leaders', [SalesTeamController::class, 'myTeamGroupLeaders']);
            Route::get('team/executive-director-lines', [ExecutiveDirectorLineController::class, 'forMyLedTeam']);
            Route::post('team/executive-director-lines/{id}/team-groups', [ExecutiveDirectorLineController::class, 'syncTeamGroupsForMyLedTeam'])->whereNumber('id');
            Route::get('member/executive-director-lines', [ExecutiveDirectorLineController::class, 'forSalesMember']);
            Route::get('manager/executive-director-lines/{salesUserId}', [ExecutiveDirectorLineController::class, 'forSalesMemberByManager'])->whereNumber('salesUserId');

            // Team-group leader context
            Route::get('team-group/led-team', [SalesTeamController::class, 'myLedTeamAsGroupLeader']);
            Route::get('team-group/led-groups', [SalesTeamController::class, 'myLedGroups']);
            Route::get('team-group/members', [SalesTeamController::class, 'myGroupMembers']);

            Route::get('team-group/executive-director-lines', [ExecutiveDirectorLineController::class, 'forGroupLeader']);
            Route::post('team-group/executive-director-lines/{id}/members', [ExecutiveDirectorLineController::class, 'syncMembersForGroupLeader'])->whereNumber('id');
            // Standalone executive-director lines (line_type + value only; not linked to sales targets)
            Route::get('executive-director-lines', [ExecutiveDirectorLineController::class, 'index']);
            Route::post('executive-director-lines', [ExecutiveDirectorLineController::class, 'store']);
            Route::get('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'show'])->whereNumber('id');
            Route::put('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'update'])->whereNumber('id');
            Route::delete('executive-director-lines/{id}', [ExecutiveDirectorLineController::class, 'destroy'])->whereNumber('id');

            // Projects
            Route::get('projects', [SalesProjectController::class, 'index']);
            Route::get('projects/{contractId}', [SalesProjectController::class, 'show']);
            Route::get('projects/{contractId}/units', [SalesProjectController::class, 'units']);
            Route::get('units/{id}/pdf', [SalesProjectController::class, 'unitPdf']);
            Route::get('units/{unitId}/pdf-data', [SalesProjectController::class, 'unitPdfData'])->whereNumber('unitId');
            Route::post('units/{unitId}/developer-package/send', [SalesProjectController::class, 'sendDeveloperPackage'])->whereNumber('unitId');

            // Unit Search (cross-project)
            Route::get('units/search', [SalesUnitSearchController::class, 'search']);
            Route::get('units/filters', [SalesUnitSearchController::class, 'filters']);
            Route::get('units/search-alerts', [SalesUnitSearchAlertController::class, 'index']);
            Route::post('units/search-alerts', [SalesUnitSearchAlertController::class, 'store']);
            Route::get('units/search-alerts/{alert}', [SalesUnitSearchAlertController::class, 'show']);
            Route::patch('units/search-alerts/{alert}', [SalesUnitSearchAlertController::class, 'update']);
            Route::delete('units/search-alerts/{alert}', [SalesUnitSearchAlertController::class, 'destroy']);

            // Reservation context
            Route::get('units/{unitId}/reservation-context', [SalesReservationController::class, 'context']);

            Route::group([], function () {
                Route::get('reservations/eligible-participants', [SalesReservationController::class, 'eligibleParticipants']);
                Route::get('reservations/{reservation}/participants', [SalesReservationController::class, 'participantsIndex'])->whereNumber('reservation');
            });
            Route::put('reservations/{reservation}/participants', [SalesReservationController::class, 'participantsSync'])->whereNumber('reservation');

            // Reservations
            Route::post('reservations', [SalesReservationController::class, 'store']);
            Route::get('reservations', [SalesReservationController::class, 'index']);
            Route::get('reservations/{id}', [SalesReservationController::class, 'show']);
            Route::post('reservations/{id}/confirm', [SalesReservationController::class, 'confirm']);
            Route::post('reservations/{id}/cancel', [SalesReservationController::class, 'cancel']);
            Route::post('reservations/{id}/actions', [SalesReservationController::class, 'storeAction']);
            Route::get('reservations/{id}/voucher', [SalesReservationController::class, 'downloadVoucher']);
            Route::get('reservations/{id}/voucher-data', [SalesReservationController::class, 'voucherData']);

            // My targets
            Route::get('targets/my', [SalesTargetController::class, 'my']);
            Route::get('targets/by-project/{contractId}', [SalesTargetController::class, 'byProject']);
            Route::patch('targets/{id}', [SalesTargetController::class, 'update']);

            // My attendance
            Route::get('attendance/my', [SalesAttendanceController::class, 'my']);

            // My assignments (for sales leaders)
            Route::get('assignments/my', [SalesProjectController::class, 'getMyAssignments']);

            // Team management (leader only)
            Route::group([], function () {
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

                Route::get('tasks/projects', [MarketingTaskController::class, 'projects']);
                Route::get('tasks/projects/{contractId}', [MarketingTaskController::class, 'showProject']);
                Route::post('marketing-tasks', [MarketingTaskController::class, 'store']);
                Route::patch('marketing-tasks/{id}', [MarketingTaskController::class, 'update']);
            });

            // Waiting List Routes
            Route::prefix('waiting-list')->group(function () {
                Route::get('/', [WaitingListController::class, 'index']);
                Route::get('/unit/{unitId}', [WaitingListController::class, 'getByUnit']);
                Route::post('/', [WaitingListController::class, 'store']);
                Route::post('/{id}/convert', [WaitingListController::class, 'convert']);
                Route::delete('/{id}', [WaitingListController::class, 'cancel']);
            });

            // Sales Insights Routes
            Route::get('sold-units', [SalesInsightsController::class, 'soldUnits']);
            Route::get('sold-units/{unitId}/commission-summary', [SalesInsightsController::class, 'soldUnitCommissionSummary']);
            Route::get('deposits/management', [SalesInsightsController::class, 'depositsManagement']);
            Route::get('deposits/follow-up', [SalesInsightsController::class, 'depositsFollowUp']);

            // Sales Analytics Routes
            Route::prefix('analytics')->group(function () {
                Route::get('dashboard', [SalesAnalyticsController::class, 'dashboard']);
                Route::get('sold-units', [SalesAnalyticsController::class, 'soldUnits']);
                Route::get('deposits/stats/project/{contractId}', [SalesAnalyticsController::class, 'depositStatsByProject']);
                Route::get('commissions/stats/employee/{userId}', [SalesAnalyticsController::class, 'commissionStatsByEmployee']);
                Route::get('commissions/monthly-report', [SalesAnalyticsController::class, 'monthlyCommissionReport']);
            });
        });


            // Create an admin prefix group with admin middleware
        Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

                Route::prefix('employees')->group(function () {
                    Route::get('/roles', [RegisterController::class, 'list_roles']);
                    Route::post('/add_employee', [RegisterController::class, 'add_employee']);
                        Route::get('/list_employees', [RegisterController::class, 'list_employees']);
                        Route::get('/show_employee/{id}', [RegisterController::class, 'show_employee']);
                        Route::put('/update_employee/{id}', [RegisterController::class, 'update_employee']);
                        Route::delete('/delete_employee/{id}', [RegisterController::class, 'delete_employee']);
                        Route::patch('/restore/{id}', [RegisterController::class, 'restore_employee']);
                });

                Route::prefix('contracts')->group(function () {
                    Route::get('/adminIndex', [ContractController::class, 'adminIndex']);
                    Route::patch('adminUpdateStatus/{id}', [ContractController::class, 'adminUpdateStatus']);
                });

                Route::prefix('notifications')->group(function () {
                    // Get admin's own notifications
                    Route::get('/', [NotificationController::class, 'getAdminNotifications']);
                    Route::post('/send-to-user', [NotificationController::class, 'sendToUser']);
                    Route::post('/send-public', [NotificationController::class, 'sendPublic']);
                    // Get all notifications of specific user
                    Route::get('/user/{userId}', [NotificationController::class, 'getUserNotificationsByAdmin']);
                    // Get all public notifications
                    Route::get('/public', [NotificationController::class, 'getAllPublicNotifications']);
                });

                // ==========================================
                // ADMIN SALES API - Project Assignments
                // ==========================================
                Route::prefix('sales')->group(function () {
                    Route::post('project-assignments', [SalesProjectController::class, 'assignProject']);
                });

                // CSV: list all imports + types (admin). Upload endpoints return import_id; poll via GET …/csv/imports.
                Route::prefix('csv')->group(function () {
                    Route::get('types', [CsvImportController::class, 'types']);
                    Route::get('imports', [CsvImportController::class, 'index']);

                    Route::post('contracts/import_csv', [ContractController::class, 'import_contracts_csv']);

                    Route::post('contracts/import_info_csv/{contractId}', [ContractInfoController::class, 'import_csv'])->whereNumber('contractId');

                    Route::post('second-party-data/import_csv/{contractId}', [SecondPartyDataController::class, 'import_csv'])->whereNumber('contractId');

                    Route::post('teams/import_csv', [TeamController::class, 'import_csv']);

                    Route::post('employees/import_employees_csv', [RegisterController::class, 'import_employees_csv']);

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
            Route::post('/{id}/approve', [ExclusiveProjectController::class, 'approve']);
            Route::post('/{id}/reject', [ExclusiveProjectController::class, 'reject']);
            Route::put('/{id}/contract', [ExclusiveProjectController::class, 'completeContract']);
            Route::get('/{id}/export', [ExclusiveProjectController::class, 'exportContract']);
        });

        // ==========================================
        // MARKETING DEPARTMENT ROUTES

        Route::prefix('marketing')->middleware(['auth:sanctum'])->group(function () {

            // Dashboard
            Route::get('dashboard', [MarketingDashboardController::class, 'index']);

            // Projects
            Route::get('projects', [MarketingProjectController::class, 'index']);
            Route::get('projects/{id}', [MarketingProjectController::class, 'show']);

            // Developer Plans
            Route::get('developer-plans/{contractId}', [DeveloperMarketingPlanController::class, 'show']);
            Route::get('developer-plans/{contractId}/pdf', [DeveloperMarketingPlanController::class, 'downloadPdf'])->whereNumber('contractId');
            Route::get('reports/developer-plan/{contractId}/pdf-data', [DeveloperMarketingPlanController::class, 'pdfData'])->whereNumber('contractId');
            Route::post('developer-plans/calculate-budget', [DeveloperMarketingPlanController::class, 'calculateBudget']);
            Route::post('developer-plans', [DeveloperMarketingPlanController::class, 'store']);

            // Users list for marketing (e.g. employee-plans dropdown) – same as GET /hr/users
            Route::get('users', [HrUserController::class, 'index']);

            // Employee Plans (GET with query ?project_id= supported; must be before employee-plans/{planId})
            Route::get('employee-plans', [EmployeeMarketingPlanController::class, 'index']);
            Route::get('employee-plans/project/{projectId}', [EmployeeMarketingPlanController::class, 'index']);
            Route::get('employee-plans/pdf-data', [EmployeeMarketingPlanController::class, 'pdfData']);
            Route::get('employee-plans/{planId}', [EmployeeMarketingPlanController::class, 'show']);
            Route::post('employee-plans', [EmployeeMarketingPlanController::class, 'store']);
            Route::post('employee-plans/auto-generate', [EmployeeMarketingPlanController::class, 'autoGenerate']);

            // Expected Sales
            Route::get('expected-sales/{projectId}', [ExpectedSalesController::class, 'calculate']);
            Route::put('settings/conversion-rate', [ExpectedSalesController::class, 'updateConversionRate']);

            // Tasks
            Route::get('tasks', [MarketingModuleTaskController::class, 'index']);
            Route::post('tasks', [MarketingModuleTaskController::class, 'store']);
            Route::put('tasks/{taskId}', [MarketingModuleTaskController::class, 'update']);
            Route::patch('tasks/{taskId}/status', [MarketingModuleTaskController::class, 'updateStatus']);

            // Team Management
            Route::post('projects/{projectId}/team', [TeamManagementController::class, 'assignTeam']);
            Route::get('projects/{projectId}/team', [TeamManagementController::class, 'getTeam']);
            Route::get('projects/{projectId}/recommend-employee', [TeamManagementController::class, 'recommendEmployee']);

            // Marketing Employees (view-only for marketing department)
            Route::get('employees', [MarketingEmployeeController::class, 'index']);
            Route::get('employees/{id}', [MarketingEmployeeController::class, 'show'])->whereNumber('id');

            // Leads
            Route::get('leads', [LeadController::class, 'index']);
            Route::post('leads', [LeadController::class, 'store']);
            Route::put('leads/{leadId}', [LeadController::class, 'update']);

            // Reports
            Route::get('reports/project/{projectId}', [MarketingReportController::class, 'projectPerformance']);
            Route::get('reports/budget', [MarketingReportController::class, 'budgetReport']);
            Route::get('reports/expected-bookings', [MarketingReportController::class, 'expectedBookingsReport']);
            Route::get('reports/employee/{userId}', [MarketingReportController::class, 'employeePerformance']);
            Route::get('reports/export/{planId}', [MarketingReportController::class, 'exportPlan']);
            Route::get('reports/distribution/project/{projectId}', [MarketingReportController::class, 'exportDistributionByProject'])->whereNumber('projectId');
            Route::get('reports/distribution/{planId}', [MarketingReportController::class, 'exportDistribution']);

            // Settings
            Route::get('settings', [MarketingSettingsController::class, 'index']);
            Route::put('settings/{key}', [MarketingSettingsController::class, 'update']);
        });

    });


    Route::prefix('hr')->middleware(['auth:sanctum'])->group(function () {
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

            // Sub-groups per team (CRUD) — also available under project_management/team-groups
            Route::prefix('team-groups')->group(function () {
                Route::get('{groupId}/members', [HrTeamController::class, 'membersOfTeamGroup'])->whereNumber('groupId');
                Route::delete('{groupId}/members/{userId}', [HrTeamController::class, 'removeMemberFromTeamGroup'])->whereNumber(['groupId', 'userId']);
                Route::get('/', [TeamGroupController::class, 'index']);
                Route::post('/', [TeamGroupController::class, 'store']);
                Route::post('/{id}/leader', [TeamGroupLeaderController::class, 'assign'])->whereNumber('id');
                Route::delete('/{id}/leader', [TeamGroupLeaderController::class, 'remove'])->whereNumber('id');
                Route::get('/{id}', [TeamGroupController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [TeamGroupController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [TeamGroupController::class, 'destroy'])->whereNumber('id');
            });

            Route::get('team-group-leaders', [TeamGroupLeaderController::class, 'index']);

            Route::prefix('teams')->group(function () {
                Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');
                Route::get('/contracts/locations/{teamId}', [TeamController::class, 'contractLocations'])->whereNumber('teamId');
                Route::get('/sales-average/{teamId}', [TeamController::class, 'salesAverage'])->whereNumber('teamId');
                Route::get('/getTeamsForContract/{contractId}', [ContractController::class, 'getTeamsForContract']);

                Route::get('/', [HrTeamController::class, 'index']);
                Route::post('/', [HrTeamController::class, 'store']);
                Route::get('sales-leaders', [HrTeamController::class, 'salesLeadersIndex']);
                Route::get('/{id}/members', [HrTeamController::class, 'members'])->whereNumber('id');
                Route::post('/{id}/members', [HrTeamController::class, 'assignMember'])->whereNumber('id');
                Route::post('/{id}/sales-leader', [HrTeamController::class, 'assignSalesLeader'])->whereNumber('id');
                Route::delete('/{id}/sales-leader/{userId}', [HrTeamController::class, 'removeSalesLeader'])->whereNumber(['id', 'userId']);
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




    Route::prefix('inventory')->middleware(['auth:sanctum'])->group(function () {

                // Contracts
                Route::prefix('contracts')->group(function () {
                    Route::get('/show/{id}', [ContractController::class, 'show']);
                    Route::get('/show/{id}/pdf', [ContractController::class, 'showPdf'])->whereNumber('id');
                    Route::get('/admin-index', [ContractController::class, 'adminIndex']);
                });

                // Second party data
                Route::prefix('second-party-data')->group(function () {
                    Route::get('/show/{id}', [SecondPartyDataController::class, 'show']);
                    Route::get('/{contractId}/pdf', [SecondPartyDataController::class, 'downloadPdf'])->whereNumber('contractId');
                });

                // Contract units
                Route::prefix('contracts/units')->group(function () {
                    Route::get('/show/{contractId}', [ContractUnitController::class, 'indexByContract']);
                });

                // Team contract locations & dashboard
                Route::get('/contracts/locations', [ContractController::class, 'locations']);
                Route::get('/contracts/agency-overview', [ContractController::class, 'inventoryAgencyOverview']);
                Route::get('/dashboard', [ContractController::class, 'inventoryDashboard']);

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
    Route::prefix('accounting')->middleware(['auth:sanctum'])->group(function () {

            Route::get('dashboard', [AccountingDashboardController::class, 'index']);

            // Commission management
            Route::get('sold-units', [AccountingCommissionController::class, 'index']);
            Route::get('commission-distribution-types', [AccountingCommissionController::class, 'distributionTypes']);
            Route::get('marketers', [AccountingCommissionController::class, 'marketers']);
            Route::get('sold-units/{id}', [AccountingCommissionController::class, 'show']);
            Route::get('commissions/{id}/pdf-data', [AccountingCommissionController::class, 'commissionPdfData'])->whereNumber('id');
            Route::get('commissions/released', [AccountingCommissionController::class, 'released']);
            Route::post('sold-units/{id}/commission', [AccountingCommissionController::class, 'createManual']);
            Route::put('commissions/{id}/distributions', [AccountingCommissionController::class, 'updateDistributions']);
            Route::post('commissions/{id}/distributions/{distId}/approve', [AccountingCommissionController::class, 'approveDistribution']);
            Route::post('commissions/{id}/distributions/{distId}/reject', [AccountingCommissionController::class, 'rejectDistribution']);
            Route::get('commissions/{id}/summary', [AccountingCommissionController::class, 'summary']);
            Route::post('commissions/{id}/distributions/{distId}/confirm', [AccountingCommissionController::class, 'confirmPayment']);

            Route::get('project-commission-settings', [ProjectCommissionSettingController::class, 'index']);
            Route::post('project-commission-settings', [ProjectCommissionSettingController::class, 'store']);
            Route::get('project-commission-settings/{projectCommissionSetting}', [ProjectCommissionSettingController::class, 'show'])->whereNumber('projectCommissionSetting');
            Route::put('project-commission-settings/{projectCommissionSetting}', [ProjectCommissionSettingController::class, 'update'])->whereNumber('projectCommissionSetting');
            Route::post('project-commission-settings/{projectCommissionSetting}/activate', [ProjectCommissionSettingController::class, 'activate'])->whereNumber('projectCommissionSetting');

            Route::post('projects/{project}/preview-commission', [ProjectCommissionPreviewController::class, 'previewProject'])->whereNumber('project');
            Route::post('reservations/{reservation}/preview-unit-commission', [ProjectCommissionPreviewController::class, 'previewUnit'])->whereNumber('reservation');

            Route::post('reservations/{reservation}/generate-unit-commission', [UnitCommissionGenerationController::class, 'generate'])
                
                ->whereNumber('reservation');

            // Deposit management
            Route::get('deposits/pending', [AccountingDepositController::class, 'pending']);
            Route::get('deposits/{id}/pdf-data', [AccountingDepositController::class, 'depositPdfData'])->whereNumber('id');
            Route::get('deposits/follow-up', [AccountingDepositController::class, 'followUp']);
            Route::post('deposits/{id}/confirm', [AccountingDepositController::class, 'confirm']);
            Route::post('deposits/{id}/refund', [AccountingDepositController::class, 'refund']);

            // Down payment confirmations
            Route::get('pending-confirmations', [AccountingConfirmationController::class, 'index']);
            Route::get('confirmations/history', [AccountingConfirmationController::class, 'history']);
            Route::post('confirmations/{id}/confirm', [AccountingConfirmationController::class, 'confirm']);

            // Salary management
            Route::get('salaries', [AccountingSalaryController::class, 'index']);
            Route::get('salaries/{userId}', [AccountingSalaryController::class, 'show']);
            Route::post('salaries/{userId}/distribute', [AccountingSalaryController::class, 'createDistribution']);
            Route::post('salaries/distributions/{distributionId}/approve', [AccountingSalaryController::class, 'approveDistribution']);
            Route::post('salaries/distributions/{distributionId}/paid', [AccountingSalaryController::class, 'markAsPaid']);

            // Claim files: list + candidates + sold units + PDF by claim_file id
            Route::get('claim-files', [ClaimFileController::class, 'index']);
            Route::get('claim-files/candidates', [ClaimFileController::class, 'candidates']);
            Route::get('claim-files/sold-units', [ClaimFileController::class, 'soldUnitsByProject']);
            Route::get('claim-files/{id}/pdf', [ClaimFileController::class, 'download'])->whereNumber('id');
            Route::post('claim-files/{id}/pdf', [ClaimFileController::class, 'generatePdf'])->whereNumber('id');
            Route::patch('claim-files/{id}', [ClaimFileController::class, 'updateClaimFileStatus'])->whereNumber('id');
            Route::post('claim-files/combined', [ClaimFileController::class, 'generateCombined']);

            // Notifications
            Route::get('notifications', [AccountingNotificationController::class, 'index']);
            Route::post('notifications/read-all', [AccountingNotificationController::class, 'markAllAsRead']);
            Route::post('notifications/{id}/read', [AccountingNotificationController::class, 'markAsRead']);
    });

// ==========================================
    // CREDIT DEPARTMENT ROUTES
    Route::prefix('credit')->middleware(['auth:sanctum'])->group(function () {

            // Dashboard
            Route::get('dashboard', [CreditDashboardController::class, 'index']);
            Route::post('dashboard/refresh', [CreditDashboardController::class, 'refresh']);

            // Marketing developer orders (order_marketing_developers)
            Route::get('order-marketing-developers', [OrderMarketingDeveloperController::class, 'index']);
            Route::post('order-marketing-developers', [OrderMarketingDeveloperController::class, 'store']);
            Route::get('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'show']);
            Route::put('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'update']);
            Route::delete('order-marketing-developers/{id}', [OrderMarketingDeveloperController::class, 'destroy']);

            // Bookings
            Route::get('bookings', [CreditBookingController::class, 'index']);
            Route::get('bookings/confirmed', [CreditBookingController::class, 'confirmed']);
            Route::get('bookings/negotiation', [CreditBookingController::class, 'negotiation']);
            Route::get('bookings/waiting', [CreditBookingController::class, 'waiting']);
            Route::get('bookings/sold', [CreditBookingController::class, 'sold']);
            Route::get('bookings/cancelled', [CreditBookingController::class, 'cancelled']);
            Route::get('bookings/{id}', [CreditBookingController::class, 'show']);
            Route::patch('bookings/negotiation/{id}', [CreditBookingController::class, 'updateNegotiation']);
            Route::post('bookings/{id}/cancel', [CreditBookingController::class, 'cancel']);

            // Financing
            Route::get('bookings/{id}/financing', [CreditFinancingController::class, 'show']);
            Route::post('bookings/{id}/financing', [CreditFinancingController::class, 'initialize']);
            Route::post('bookings/{id}/financing/advance', [CreditFinancingController::class, 'advance']);
            Route::patch('bookings/{bookingId}/financing/stage/{stage}', [CreditFinancingController::class, 'completeStage']);
            Route::post('bookings/{bookingId}/financing/reject', [CreditFinancingController::class, 'reject']);

            // Title Transfer
            Route::post('bookings/{id}/title-transfer', [TitleTransferController::class, 'initialize']);
            Route::get('title-transfers/pending', [TitleTransferController::class, 'pending']);
            Route::patch('title-transfer/{id}/schedule', [TitleTransferController::class, 'schedule']);
            Route::patch('title-transfer/{id}/unschedule', [TitleTransferController::class, 'unschedule']);
            Route::post('title-transfer/{id}/complete', [TitleTransferController::class, 'complete']);
            Route::get('sold-projects', [TitleTransferController::class, 'soldProjects']);

            // Claim Files
          /*  Route::get('claim-files', [ClaimFileController::class, 'index']);
            Route::get('claim-files/candidates', [ClaimFileController::class, 'candidates']);
            Route::post('claim-files/generate-bulk', [ClaimFileController::class, 'generateBulk']);
            Route::post('claim-files/combined', [ClaimFileController::class, 'generateCombined']);
            Route::get('claim-files/{id}', [ClaimFileController::class, 'show']);
            Route::get('claim-files/{id}/pdf', [ClaimFileController::class, 'download']);
            Route::post('claim-files/{id}/pdf', [ClaimFileController::class, 'generatePdf']);
            Route::post('bookings/{id}/claim-file', [ClaimFileController::class, 'generate']);
            */
            // Notifications
            Route::get('notifications', [CreditNotificationController::class, 'index']);
            Route::post('notifications/read-all', [CreditNotificationController::class, 'markAllAsRead']);
            Route::post('notifications/{id}/read', [CreditNotificationController::class, 'markAsRead']);
    });

// ==========================================
// AI CALLING ROUTES
    Route::prefix('ai/calls')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [AiCallController::class, 'index']);
        Route::get('/analytics', [AiCallController::class, 'analytics']);
        Route::get('/scripts', [AiCallController::class, 'scripts']);
        Route::post('/scripts', [AiCallController::class, 'storeScript']);
        Route::put('/scripts/{id}', [AiCallController::class, 'updateScript']);
        Route::delete('/scripts/{id}', [AiCallController::class, 'deleteScript']);
        Route::post('/initiate', [AiCallController::class, 'initiate']);
        Route::post('/bulk', [AiCallController::class, 'bulkInitiate']);
        Route::get('/{id}', [AiCallController::class, 'show']);
        Route::get('/{id}/transcript', [AiCallController::class, 'transcript']);
        Route::post('/{id}/retry', [AiCallController::class, 'retry']);
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
    Route::prefix('webhooks/twilio')->group(function () {
        Route::post('/voice/{callId}', [TwilioWebhookController::class, 'handleVoice']);
        Route::post('/gather/{callId}', [TwilioWebhookController::class, 'handleGather']);
        Route::post('/status/{callId}', [TwilioWebhookController::class, 'handleStatus']);
        Route::post('/fallback/{callId}', [TwilioWebhookController::class, 'handleFallback']);
    });

    // ==========================================
    Route::prefix('ads')->middleware(['auth:sanctum'])->group(function () {
        Route::get('accounts', [AdsInsightsController::class, 'accounts']);
        Route::post('accounts', [AdsAccountsController::class, 'upsert']);
        Route::patch('accounts/{id}', [AdsAccountsController::class, 'update'])->whereNumber('id');
        Route::post('accounts/{id}/refresh', [AdsAccountsController::class, 'refresh'])->whereNumber('id');
        Route::post('accounts/{id}/test', [AdsAccountsController::class, 'test'])->whereNumber('id');
        Route::get('campaigns', [AdsInsightsController::class, 'campaigns']);
        Route::get('adsets', [AdsInsightsController::class, 'adSets']);
        Route::get('ads', [AdsInsightsController::class, 'ads']);
        Route::get('insights', [AdsInsightsController::class, 'insights']);
        Route::get('leads', [AdsLeadsController::class, 'index']);
        Route::get('leads/stored', [AdsLeadsController::class, 'stored']);
        Route::get('leads/export', [AdsLeadsController::class, 'export']);
        Route::post('leads/export-snap', [AdsLeadsController::class, 'exportSnap']);
        Route::post('leads/sync', [AdsLeadsController::class, 'triggerSync']);
        Route::get('exports', [AdsExportsController::class, 'index']);
        Route::post('exports/leads', [AdsExportsController::class, 'createLeadsCsv']);
        Route::get('exports/{id}', [AdsExportsController::class, 'show'])->whereNumber('id');
        Route::get('exports/{id}/download', [AdsExportsController::class, 'download'])->whereNumber('id');
        Route::get('ops/sync-runs', [AdsOpsController::class, 'syncRuns']);
        Route::get('reports/platform-performance', [AdsReportingController::class, 'platformPerformance']);
        Route::get('reports/campaign-performance', [AdsReportingController::class, 'campaignPerformance']);
        Route::get('reports/daily-trend', [AdsReportingController::class, 'dailyTrend']);
        Route::post('sync', [AdsInsightsController::class, 'triggerSync']);
        Route::post('outcomes', [AdsOutcomeController::class, 'store']);
        Route::get('outcomes/status', [AdsOutcomeController::class, 'status']);
    });

    Route::prefix('sales')->middleware(['auth:sanctum'])->group(function () {

        // Negotiation Approvals
        Route::get('negotiations/pending', [NegotiationApprovalController::class, 'index']);
        Route::post('negotiations/{id}/approve', [NegotiationApprovalController::class, 'approve']);
        Route::post('negotiations/{id}/reject', [NegotiationApprovalController::class, 'reject']);

        // Payment Plans
        Route::get('reservations/{id}/payment-plan', [PaymentPlanController::class, 'show']);
        Route::post('reservations/{id}/payment-plan', [PaymentPlanController::class, 'store']);
        Route::put('payment-installments/{id}', [PaymentPlanController::class, 'update']);
        Route::delete('payment-installments/{id}', [PaymentPlanController::class, 'destroy']);
    });
