<?php

use App\Services\Pdf\PdfFactory;
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
// TASKS PAGE (Add Task form with section → assignee filtering)
// ==========================================

Route::get('/tasks', function () {
    return view('tasks.index');
});

// ==========================================
// CHAT SYSTEM TEST PAGE
// ==========================================

// Chat test page (requires authentication)
Route::get('/chat/test', function () {
    if (!auth()->check()) {
        return redirect('/login')->with('error', 'يجب تسجيل الدخول لاستخدام نظام الدردشة');
    }

    return view('chat.test');
})->middleware('auth');

// ==========================================
// LOCAL: PDF / Blade preview (dummy data)
// ==========================================

if (app()->environment('local')) {
    $devPdfSampleData = static function (): array {
        return [
            'contract_id' => '999',
            'rows' => [
                ['label' => 'اسم المشروع', 'value' => 'مشروع تجريبي للمعاينة'],
                ['label' => 'الحي', 'value' => 'حي الاختبار'],
                ['label' => 'نوع العقد', 'value' => 'بيع'],
                ['label' => 'الجهة', 'value' => 'شمال'],
                ['label' => 'نص عربي طويل', 'value' => 'هذا سطر لاختبار التفاف النص والاتجاه من اليمين إلى اليسار في مخرجات mPDF.'],
                ['label' => 'رقم / LTR', 'value' => 'CR-1234567'],
            ],
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    };

    Route::get('/dev/pdf/preview-html', function () use ($devPdfSampleData) {
        return view('pdfs.dev_sample', $devPdfSampleData());
    });

    Route::get('/dev/pdf/preview-pdf', function () use ($devPdfSampleData) {
        return PdfFactory::download(
            'pdfs.dev_sample',
            $devPdfSampleData(),
            'dev_sample.pdf'
        );
    });
}
