<?php

use Illuminate\Support\Facades\Route;
use App\Events\UserNotificationEvent;
use App\Events\PublicNotificationEvent;
use App\Events\AdminNotificationEvent;

Route::get('/', function () {
    return view('welcome');
});

// ==========================================
// NOTIFICATION TEST PAGES
// ==========================================

// Admin notifications (private - requires admin login)
Route::get('/notifications/admin', function () {
    return view('notifications.admin');
});

// User notifications (private - requires user login)
Route::get('/notifications/user', function () {
    return view('notifications.user');
});

// Public notifications (no login required)
Route::get('/notifications/public', function () {
    return view('notifications.public');
});

// ==========================================
// TEST BROADCAST ROUTES (for debugging)
// ==========================================

// Test: Send to specific user
Route::get('/test/broadcast/user/{userId}', function ($userId) {
    event(new UserNotificationEvent((int) $userId, 'Test message for user ' . $userId . ' at ' . now()));
    return 'Broadcast sent to user ' . $userId;
});

// Test: Send public
Route::get('/test/broadcast/public', function () {
    event(new PublicNotificationEvent('Public test message at ' . now()));
    return 'Public broadcast sent';
});

// Test: Send to admins
Route::get('/test/broadcast/admin', function () {
    event(new AdminNotificationEvent('Admin test message at ' . now()));
    return 'Admin broadcast sent';
});

// ==========================================
// CHAT SYSTEM TEST PAGE
// ==========================================

// Chat test page (requires authentication)
Route::get('/chat/test', function () {
    // Ensure user is authenticated
    if (!auth()->check()) {
        return redirect('/login')->with('error', 'يجب تسجيل الدخول لاستخدام نظام الدردشة');
    }

    return view('chat.test');
})->middleware('auth');
