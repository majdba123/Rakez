<?php

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
use App\Http\Controllers\NotificationController;


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
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ==========================================
    // USER NOTIFICATIONS - إشعارات المستخدم الشخصية
    // ==========================================
    Route::prefix('notifications/user')->group(function () {
        Route::get('/', [NotificationController::class, 'userIndex']);
        Route::get('/unread', [NotificationController::class, 'userUnread']);
        Route::get('/unread-count', [NotificationController::class, 'userUnreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'userMarkAsRead']);
        Route::patch('/mark-all-read', [NotificationController::class, 'userMarkAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'userDestroy']);
    });

    // ==========================================
    // PUBLIC NOTIFICATIONS - الإشعارات العامة (للجميع)
    // ==========================================
    Route::get('/notifications/public', [NotificationController::class, 'publicIndex']);

    // ==========================================
    // ALL NOTIFICATIONS - جميع الإشعارات للمستخدم
    // ==========================================
    Route::get('/notifications/all', [NotificationController::class, 'getAllForUser']);
});


    /*Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassword']);
    Route::post('/reset-password', [ForgetPasswordController::class, 'resetPasswordByVerifyOtp']);
    Route::post('/resend-otp', [RegisterController::class, 'resendOtp'])->middleware('auth:sanctum');
    */

    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    // Contract Routes - Protected routes (user contracts)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/contracts/index', [ContractController::class, 'index']);
        Route::post('/contracts/store', [ContractController::class, 'store']);
        Route::get('/contracts/show/{id}', [ContractController::class, 'show']);
        Route::put('/contracts/update/{id}', [ContractController::class, 'update']);
        Route::delete('/contracts/{id}', [ContractController::class, 'destroy']);

        Route::post('/contracts/store/info/{id}', [ContractInfoController::class, 'store']);

    });

    // Project Management Routes - إدارة المشاريع
    // Only project_management and admin users can access these routes
    Route::middleware(['auth:sanctum', 'project_management'])->group(function () {

        Route::get('/contracts/index', [ContractController::class, 'adminIndex']);
        Route::patch('contracts/update-status/{id}', [ContractController::class, 'projectManagementUpdateStatus']);


        Route::prefix('second-party-data')->group(function () {
            Route::get('show/{id}', [SecondPartyDataController::class, 'show']);
            Route::post('store/{id}', [SecondPartyDataController::class, 'store']);
            Route::put('update/{id}', [SecondPartyDataController::class, 'update']);

            Route::get('/second-parties', [ContractInfoController::class, 'getAllSecondParties']);

        });

        Route::prefix('contracts/units')->group(function () {
            Route::get('show/{contractId}', [ContractUnitController::class, 'indexByContract']);
            Route::post('upload-csv/{contractId}', [ContractUnitController::class, 'uploadCsvByContract']);
            Route::post('store/{contractId}', [ContractUnitController::class, 'store']);
            Route::put('update/{unitId}', [ContractUnitController::class, 'update']);
            Route::delete('delete/{unitId}', [ContractUnitController::class, 'destroy']);
        });

        // تحديث حالة العقد - إدارة المشاريع

    });




            // Create an admin prefix group with admin middleware
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

            // ==========================================
            // ADMIN NOTIFICATIONS - إشعارات المدراء
            // ==========================================
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'adminIndex']);
                Route::get('/unread-count', [NotificationController::class, 'adminUnreadCount']);
                Route::get('/all', [NotificationController::class, 'getAllForAdmin']);
                Route::post('/', [NotificationController::class, 'adminStore']);
                Route::patch('/{id}/read', [NotificationController::class, 'adminMarkAsRead']);
                Route::patch('/mark-all-read', [NotificationController::class, 'adminMarkAllAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'adminDestroy']);
            });

            // ==========================================
            // PUBLIC NOTIFICATIONS MANAGEMENT - إدارة الإشعارات العامة
            // ==========================================
            Route::prefix('public-notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'publicAdminIndex']);
                Route::post('/', [NotificationController::class, 'publicStore']);
                Route::put('/{id}', [NotificationController::class, 'publicUpdate']);
                Route::delete('/{id}', [NotificationController::class, 'publicDestroy']);
            });
        });




    Route::get('/storage/{path}', function ($path) {
        $filePath = storage_path('app/public/' . $path);

        if (!File::exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
    })->where('path', '.*');
