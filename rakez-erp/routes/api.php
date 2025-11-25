<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

use Illuminate\Support\Facades\File;  // أضف هذا السطر في الأعلى

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
    // Add your other protected routes here
});


    /*Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassword']);
    Route::post('/reset-password', [ForgetPasswordController::class, 'resetPasswordByVerifyOtp']);
    Route::post('/resend-otp', [RegisterController::class, 'resendOtp'])->middleware('auth:sanctum');
    */

    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');




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
        });




    Route::get('/storage/{path}', function ($path) {
        $filePath = storage_path('app/public/' . $path);

        if (!File::exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
    })->where('path', '.*');
