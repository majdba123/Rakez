<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationsSeeder extends Seeder
{
    /**
     * رسائل إشعارات بالعربية السعودية.
     */
    protected array $adminMessages = [
        'مراجعة طلب الموافقة على حجز تفاوض جديد',
        'تم إضافة مشروع جديد إلى النظام',
        'متابعة تقرير المبيعات الشهري',
        'طلب من الموارد البشرية: اعتماد إجازة موظف',
        'تنبيه: عقد قارب على الانتهاء',
        'مراجعة طلب تمويل من قسم الائتمان',
        'تحديث قائمة الوحدات المتاحة للمشروع',
        'متابعة استلام عربون من العميل',
        'تنبيه: وحدة محجوزة بانتظار التأكيد',
        'طلب تصدير تقرير العمولات',
    ];

    protected array $userMessages = [
        'تم تعيينك لمشروع جديد - يرجى المراجعة',
        'لديك مهمة جديدة موعد إنجازها قريب',
        'تم تأكيد حجزك من قبل المدير',
        'تذكير: موعد متابعة مع العميل غداً',
        'تم إضافة عميل مؤهل إلى قائمتك',
        'تحديث: تغيير سعر الوحدة في المشروع',
        'متابعة: العميل وافق على العرض',
        'تنبيه: عربون جديد مستلم ويحتاج التأكيد',
    ];

    public function run(): void
    {
        $counts = SeedCounts::all();
        $adminIds = User::where('type', 'admin')->pluck('id')->all();
        $allUsers = User::pluck('id')->all();

        for ($i = 0; $i < $counts['admin_notifications']; $i++) {
            if (! $adminIds) {
                break;
            }
            AdminNotification::create([
                'user_id' => $adminIds[array_rand($adminIds)],
                'message' => $this->adminMessages[$i % count($this->adminMessages)] . ' (' . ($i + 1) . ')',
                'status' => $i % 2 === 0 ? 'pending' : 'read',
            ]);
        }

        for ($i = 0; $i < $counts['user_notifications']; $i++) {
            $isPublic = $i % 2 === 0;
            UserNotification::create([
                'user_id' => $isPublic ? null : $allUsers[array_rand($allUsers)],
                'message' => $this->userMessages[$i % count($this->userMessages)] . ' (' . ($i + 1) . ')',
                'status' => $i % 3 === 0 ? 'read' : 'pending',
            ]);
        }

        if ($allUsers) {
            $rows = [];
            $messages = [
                'إشعار: تم تحديث بيانات المشروع',
                'تذكير: موعد تسليم الوحدة قريب',
                'تم إضافة حجز جديد إلى قائمتك',
                'مراجعة مطلوبة: مستندات العميل',
                'تنبيه: دفعة عربون مستلمة',
            ];
            for ($i = 0; $i < 5; $i++) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\GenericNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $allUsers[array_rand($allUsers)],
                    'data' => json_encode([
                        'message' => $messages[$i % count($messages)],
                        'index' => $i + 1,
                    ]),
                    'read_at' => $i % 2 === 0 ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('notifications')->insert($rows);
        }
    }
}
