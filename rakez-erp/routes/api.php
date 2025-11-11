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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [ForgetPasswordController::class, 'resetPasswordByVerifyOtp']);
Route::post('/resend-otp', [RegisterController::class, 'resendOtp'])->middleware('auth:sanctum');

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);


Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/verify_otp', [RegisterController::class, 'verfication_otp'])->middleware('auth:sanctum');



Route::group(['middleware' => ['web']], function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
});



Route::group(['middleware' => ['web']], function () {
    Route::get('auth/facebook/redirect', [FacebookController::class, 'redirect']);
    Route::get('auth/facebook/callback', [FacebookController::class, 'callback']);
});





Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/SendTo/{recive_id}', [ChatController::class, 'sendMessage']);
    Route::get('/unread-messages', [ChatController::class, 'getUnreadMessages']);
    Route::post('/mark-messages-as-read/{sender_id}', [ChatController::class, 'markMessagesAsRead']);
    Route::get('/getInteractedUsers', [ChatController::class, 'getInteractedUsers']);
    Route::get('/getConversation/{reciver_id}', [ChatController::class, 'getConversation']);
    Route::post('/upload', [FileUploadController::class, 'upload']);


    Route::post('/coupons/check-status', [CouponController::class, 'checkStatus']);
    Route::get('/user_info/{user_id}', [ProfileController::class, 'user_info']);



    Route::get('/my_notification', [UserNotificationController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/read_notification', [UserNotificationController::class, 'read']);
    Route::get('/readable_massege', [UserNotificationController::class, 'readable_massege']);



});



    Route::get('/food-types/provider/{id}', [FoodTypeProductProviderController::class, 'getFoodTypesByProvider']);
    Route::get('/food-types/getProvidersByFoodType/{id}', [FoodTypeProductProviderController::class, 'getProvidersByFoodType']);

    Route::get('/food-types/index/', [FoodTypeController::class, 'index']);







Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!File::exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*');
