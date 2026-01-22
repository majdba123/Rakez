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
use App\Http\Controllers\TeamController;


use Illuminate\Support\Facades\File;  // أضف هذا السطر في الأعلى

// Broadcasting authentication route for API tokens
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Public routes (no auth required)
Route::post('/login', [LoginController::class, 'login']);





    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LoginController::class, 'logout']);

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


    Route::middleware(['auth:sanctum', 'project_management'])->group(function () {

        Route::get('/contracts/index', [ContractController::class, 'project_mange_index']);
        Route::patch('contracts/update-status/{id}', [ContractController::class, 'projectManagementUpdateStatus']);

        // Contract Teams (project management)
        Route::prefix('project_teams')->group(function () {
            Route::get('/teams/{contractId}', [ContractController::class, 'getTeamsForContract']);
            Route::post('/teams/add/{contractId}', [ContractController::class, 'addTeamsToContract']);
            Route::post('/teams/remove/{contractId}', [ContractController::class, 'removeTeamsFromContract']);
        });


        Route::prefix('second-party-data')->group(function () {
            Route::get('show/{id}', [SecondPartyDataController::class, 'show']);
            Route::post('store/{id}', [SecondPartyDataController::class, 'store']);
            Route::put('update/{id}', [SecondPartyDataController::class, 'update']);

            Route::get('/second-parties', [ContractInfoController::class, 'getAllSecondParties']);
            Route::get('/contracts-by-email', [ContractInfoController::class, 'getContractsBySecondPartyEmail']);
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

        Route::prefix('project_management/teams')->middleware(['auth:sanctum', 'project_management'])->group(function () {
            Route::get('/index', [TeamController::class, 'index']);
            Route::get('/show/{id}', [TeamController::class, 'show']);
            Route::post('/store', [TeamController::class, 'store']);
            Route::put('/update/{id}', [TeamController::class, 'update']);
            Route::delete('/delete/{id}', [TeamController::class, 'destroy']);
            Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');

        });


    });


    Route::prefix('editor')->middleware(['auth:sanctum', 'editor'])->group(function () {


        Route::prefix('contracts')->group(function () {
            Route::get('/index', [ContractController::class, 'editor_index']);
            Route::get('/show/{id}', [ContractController::class, 'show_editor']);
        });

        // Montage Department - قسم المونتاج
        Route::prefix('montage-department')->group(function () {
            Route::get('show/{contractId}', [MontageDepartmentController::class, 'show']);
            Route::post('store/{contractId}', [MontageDepartmentController::class, 'store']);
            Route::put('update/{contractId}', [MontageDepartmentController::class, 'update']);
        });

    });




    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

            Route::prefix('employees')->group(function () {
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
    });

    // (moved /storage route outside auth:sanctum group)


    Route::prefix('hr')->middleware(['auth:sanctum', 'hr'])->group(function () {
        Route::post('/add_employee', [RegisterController::class, 'add_employee']);
        Route::get('/list_employees', [RegisterController::class, 'list_employees']);
        Route::get('/show_employee/{id}', [RegisterController::class, 'show_employee']);
        Route::put('/update_employee/{id}', [RegisterController::class, 'update_employee']);
        Route::delete('/delete_employee/{id}', [RegisterController::class, 'delete_employee']);



                // Contract Teams (project management)
        Route::prefix('teams')->group(function () {
            Route::get('/index/{contractId}', [ContractController::class, 'getTeamsForContract_HR']);
            Route::get('/contracts/{teamId}', [TeamController::class, 'contracts'])->whereNumber('teamId');
            Route::get('/getTeamsForContract/{contractId}', [ContractController::class, 'getTeamsForContract']);


        });
    });



    Route::prefix('teams')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/index', [TeamController::class, 'index']);
        Route::get('/show/{id}', [TeamController::class, 'show']);
        // Clean + secure: status is query param, teamId is route param
    });

});

// Public file access from storage/app/public (used for cv/contract URLs)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!File::exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*');

