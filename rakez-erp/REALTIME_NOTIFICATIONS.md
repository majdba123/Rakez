# 🔔 نظام الإشعارات الفورية - Real-time Notifications System

## فهرس المحتويات
1. [نظرة عامة](#نظرة-عامة)
2. [المتطلبات](#المتطلبات)
3. [بنية النظام](#بنية-النظام)
4. [شرح الملفات](#شرح-الملفات)
5. [كيف يعمل النظام](#كيف-يعمل-النظام)
6. [إعداد البيئة](#إعداد-البيئة)
7. [تشغيل النظام](#تشغيل-النظام)
8. [اختبار النظام](#اختبار-النظام)
9. [API Endpoints](#api-endpoints)
10. [الربط مع الواجهة الأمامية](#الربط-مع-الواجهة-الأمامية)
11. [استكشاف الأخطاء](#استكشاف-الأخطاء)

---

## نظرة عامة

نظام إشعارات فورية مبني على Laravel Reverb يقوم بإرسال إشعارات لحظية لجميع المستخدمين من نوع "admin" عند إضافة موظف جديد للنظام.

### التقنيات المستخدمة:
- **Laravel Reverb**: خادم WebSocket للاتصال الفوري
- **Laravel Broadcasting**: نظام البث في Laravel
- **Laravel Notifications**: نظام الإشعارات
- **Laravel Echo**: مكتبة JavaScript للاستماع للأحداث
- **Pusher Protocol**: بروتوكول الاتصال

---

## المتطلبات

### متطلبات الخادم:
- PHP 8.2+
- Laravel 12.x
- Composer

### حزم PHP المطلوبة:
```json
{
    "laravel/reverb": "^1.6"
}
```

### حزم JavaScript المطلوبة:
```json
{
    "laravel-echo": "^2.x",
    "pusher-js": "^8.x"
}
```

---

## بنية النظام

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Postman/API   │────▶│  Laravel Server  │────▶│  Queue Worker   │
│  (Add Employee) │     │   (port 8000)    │     │                 │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                                                          ▼
                        ┌──────────────────┐     ┌─────────────────┐
                        │   Admin Browser  │◀────│  Reverb Server  │
                        │  (Notification)  │     │   (port 8080)   │
                        └──────────────────┘     └─────────────────┘
```

### تدفق البيانات:
1. **API Request**: يرسل المستخدم طلب إضافة موظف
2. **Laravel Controller**: يستقبل الطلب ويعالجه
3. **Service Layer**: ينشئ الموظف ويطلق الحدث
4. **Event Broadcasting**: يتم بث الحدث عبر Queue
5. **Reverb Server**: يستقبل الحدث ويبثه للقنوات المشتركة
6. **Laravel Echo**: يستقبل الحدث في المتصفح
7. **UI Update**: يتم تحديث الواجهة وعرض الإشعار

---

## شرح الملفات

### 1. ملف الحدث (Event)
📁 `app/Events/EmployeeCreated.php`

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class EmployeeCreated implements ShouldBroadcast
{
    public User $employee;

    public function __construct(User $employee)
    {
        $this->employee = $employee;
    }

    // القناة الخاصة بالمدراء فقط
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-notifications'),
        ];
    }

    // اسم الحدث الذي يتم الاستماع له
    public function broadcastAs(): string
    {
        return 'employee.created';
    }

    // البيانات التي يتم إرسالها
    public function broadcastWith(): array
    {
        return [
            'id' => $this->employee->id,
            'name' => $this->employee->name,
            'email' => $this->employee->email,
            'type' => $this->employee->type,
            'message' => 'تم إضافة موظف جديد: ' . $this->employee->name,
        ];
    }
}
```

**شرح الكود:**
- `ShouldBroadcast`: يجعل الحدث قابل للبث
- `PrivateChannel`: قناة خاصة تتطلب مصادقة
- `broadcastAs()`: يحدد اسم الحدث
- `broadcastWith()`: يحدد البيانات المرسلة

---

### 2. ملف الإشعار (Notification)
📁 `app/Notifications/NewEmployeeNotification.php`

```php
<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewEmployeeNotification extends Notification
{
    protected User $employee;

    public function __construct(User $employee)
    {
        $this->employee = $employee;
    }

    // قنوات التوصيل: قاعدة البيانات + البث
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    // للحفظ في قاعدة البيانات
    public function toArray(object $notifiable): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'message' => 'تم إضافة موظف جديد',
        ];
    }

    // للبث الفوري
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'employee_name' => $this->employee->name,
            'message' => 'تم إضافة موظف جديد',
        ]);
    }
}
```

**الفرق بين Event و Notification:**
| Event | Notification |
|-------|-------------|
| يبث لقناة معينة | يرسل لمستخدم معين |
| لا يحفظ في قاعدة البيانات | يمكن حفظه في قاعدة البيانات |
| مناسب للأحداث العامة | مناسب للإشعارات الشخصية |

---

### 3. ملف القنوات (Channels)
📁 `routes/channels.php`

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// قناة إشعارات المستخدم الشخصية
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// قناة إشعارات المدراء - فقط المستخدمين من نوع admin
Broadcast::channel('admin-notifications', function (User $user) {
    return $user->type === 'admin';
});
```

**شرح:**
- `Broadcast::channel()`: يحدد من يمكنه الاستماع للقناة
- `return true`: يسمح بالاشتراك
- `return false`: يرفض الاشتراك

---

### 4. ملف Service
📁 `app/Services/registartion/register.php`

```php
// في دالة register()
public function register(array $data): User
{
    // ... إنشاء الموظف ...
    $user = User::create($userData);

    // إرسال إشعار لجميع المدراء
    $this->notifyAdmins($user);

    // بث الحدث للاستماع الفوري
    event(new EmployeeCreated($user));

    return $user;
}

// دالة إرسال الإشعارات للمدراء
protected function notifyAdmins(User $employee): void
{
    $admins = User::where('type', 'admin')->get();
    Notification::send($admins, new NewEmployeeNotification($employee));
}
```

---

### 5. إعدادات البث (Broadcasting)
📁 `config/broadcasting.php`

```php
<?php

return [
    // استخدام Reverb كمشغل افتراضي
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
        ],
    ],
];
```

---

### 6. إعدادات Reverb
📁 `config/reverb.php`

```php
return [
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
        ],
    ],
    
    'apps' => [
        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'allowed_origins' => ['*'],
            ],
        ],
    ],
];
```

---

### 7. JavaScript Client
📁 `resources/js/bootstrap.js`

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

---

## إعداد البيئة

### ملف `.env`

```env
# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb Server Configuration
REVERB_APP_ID=887940
REVERB_APP_KEY=jgpli2fbp0v6n0jaqdqo
REVERB_APP_SECRET=kz2lgk62j5cgu81el1ix
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite (للواجهة الأمامية)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### تثبيت الحزم

```bash
# تثبيت حزم PHP
composer require laravel/reverb

# تثبيت حزم JavaScript
npm install laravel-echo pusher-js

# بناء الملفات
npm run build
```

---

## تشغيل النظام

### الخطوة 1: تشغيل خادم Laravel
```bash
php artisan serve
# يعمل على http://127.0.0.1:8000
```

### الخطوة 2: تشغيل خادم Reverb
```bash
php artisan reverb:start
# يعمل على localhost:8080
```

### الخطوة 3: تشغيل Queue Worker
```bash
php artisan queue:work
# يعالج الأحداث المنتظرة
```

### أو تشغيل الكل معاً (PowerShell):
```powershell
Start-Process powershell -ArgumentList "php artisan serve"
Start-Process powershell -ArgumentList "php artisan reverb:start"
Start-Process powershell -ArgumentList "php artisan queue:work"
```

---

## اختبار النظام

### الخطوة 1: فتح صفحة الإشعارات
افتح المتصفح على: `http://127.0.0.1:8000/admin/notifications`

### الخطوة 2: تسجيل الدخول
- **البريد**: `admin@gmail.com`
- **كلمة المرور**: `password`

### الخطوة 3: إضافة موظف عبر Postman

#### أولاً: الحصول على Token
```http
POST http://127.0.0.1:8000/api/login
Content-Type: application/json

{
    "email": "admin@gmail.com",
    "password": "password"
}
```

**الرد:**
```json
{
    "token": "1|abc123xyz..."
}
```

#### ثانياً: إضافة موظف
```http
POST http://127.0.0.1:8000/api/admin/employees/add_employee
Authorization: Bearer 1|abc123xyz...
Content-Type: application/json

{
    "name": "أحمد محمد",
    "email": "ahmed@example.com",
    "phone": "+966501234567",
    "password": "Password123",
    "type": 0
}
```

**أنواع الموظفين (type):**
| القيمة | النوع |
|--------|-------|
| 0 | marketing |
| 1 | admin |
| 2 | project_acquisition |
| 3 | project_management |
| 4 | editor |
| 5 | sales |
| 6 | accounting |
| 7 | credit |

### الخطوة 4: مشاهدة الإشعار
سيظهر الإشعار فوراً في صفحة المتصفح بدون إعادة تحميل!

---

## API Endpoints

### إدارة الإشعارات

| Method | Endpoint | الوصف |
|--------|----------|-------|
| GET | `/api/notifications` | جلب جميع الإشعارات |
| GET | `/api/notifications/unread` | جلب الإشعارات غير المقروءة |
| GET | `/api/notifications/unread-count` | عدد الإشعارات غير المقروءة |
| PATCH | `/api/notifications/{id}/read` | تحديد إشعار كمقروء |
| PATCH | `/api/notifications/mark-all-read` | تحديد الكل كمقروء |
| DELETE | `/api/notifications/{id}` | حذف إشعار |

### أمثلة:

#### جلب الإشعارات
```http
GET http://127.0.0.1:8000/api/notifications
Authorization: Bearer YOUR_TOKEN
```

#### جلب عدد غير المقروءة
```http
GET http://127.0.0.1:8000/api/notifications/unread-count
Authorization: Bearer YOUR_TOKEN
```

**الرد:**
```json
{
    "count": 5
}
```

---

## الربط مع الواجهة الأمامية

### React / Vue.js Example

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// إعداد Echo
window.Pusher = Pusher;
const echo = new Echo({
    broadcaster: 'reverb',
    key: 'your-reverb-key',
    wsHost: 'localhost',
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// تعيين Token للمصادقة
echo.connector.options.auth = {
    headers: {
        Authorization: `Bearer ${yourAuthToken}`
    }
};

// الاستماع للإشعارات
echo.private('admin-notifications')
    .listen('.employee.created', (event) => {
        console.log('موظف جديد:', event);
        
        // تحديث الواجهة
        showNotification(event.message);
        updateNotificationBadge();
    });
```

### Flutter / Mobile Example

```dart
// استخدام pusher_client
import 'package:pusher_client/pusher_client.dart';

PusherClient pusher = PusherClient(
    'your-reverb-key',
    PusherOptions(
        host: 'localhost',
        port: 8080,
        encrypted: false,
    ),
);

Channel channel = pusher.subscribe('private-admin-notifications');

channel.bind('employee.created', (event) {
    print('New employee: ${event.data}');
    // Update UI
});
```

---

## استكشاف الأخطاء

### المشكلة 1: لا يتم الاتصال بـ WebSocket

**الأسباب المحتملة:**
- خادم Reverb غير مشغل
- المنفذ 8080 مشغول
- إعدادات `.env` غير صحيحة

**الحل:**
```bash
# تأكد أن Reverb يعمل
php artisan reverb:start --debug

# تحقق من المنفذ
netstat -an | findstr 8080
```

### المشكلة 2: خطأ في المصادقة (403 Forbidden)

**الأسباب المحتملة:**
- Token غير صالح
- المستخدم ليس admin
- `routes/channels.php` غير صحيح

**الحل:**
```php
// تأكد من صحة channels.php
Broadcast::channel('admin-notifications', function (User $user) {
    \Log::info('Auth check for user: ' . $user->id . ' type: ' . $user->type);
    return $user->type === 'admin';
});
```

### المشكلة 3: الإشعارات لا تصل

**الأسباب المحتملة:**
- Queue Worker غير مشغل
- الحدث لا يُطلق

**الحل:**
```bash
# تشغيل Queue مع التفاصيل
php artisan queue:work --verbose

# اختبار البث يدوياً
php artisan tinker
>>> event(new \App\Events\EmployeeCreated(\App\Models\User::first()));
```

### المشكلة 4: خطأ CORS

**الحل:**
تأكد من إعدادات `allowed_origins` في `config/reverb.php`:
```php
'allowed_origins' => ['*'],
```

---

## ملخص الملفات

```
📁 rakez-erp/
├── 📁 app/
│   ├── 📁 Events/
│   │   └── EmployeeCreated.php          # حدث إضافة موظف
│   ├── 📁 Notifications/
│   │   └── NewEmployeeNotification.php  # إشعار الموظف الجديد
│   ├── 📁 Http/Controllers/
│   │   └── NotificationController.php   # API الإشعارات
│   └── 📁 Services/registartion/
│       └── register.php                 # يطلق الحدث والإشعار
├── 📁 config/
│   ├── broadcasting.php                 # إعدادات البث
│   └── reverb.php                       # إعدادات Reverb
├── 📁 routes/
│   ├── channels.php                     # قنوات البث
│   ├── api.php                          # مسارات API
│   └── web.php                          # مسارات الويب
├── 📁 resources/
│   ├── 📁 js/
│   │   └── bootstrap.js                 # إعداد Echo
│   └── 📁 views/admin/
│       └── notifications.blade.php      # صفحة الاختبار
└── 📁 database/migrations/
    └── create_notifications_table.php   # جدول الإشعارات
```

---

## المزيد من الموارد

- [Laravel Broadcasting Documentation](https://laravel.com/docs/broadcasting)
- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Laravel Echo GitHub](https://github.com/laravel/echo)

---

## 📞 الدعم

إذا واجهت أي مشاكل، تأكد من:
1. ✅ جميع الخوادم تعمل (Laravel, Reverb, Queue)
2. ✅ إعدادات `.env` صحيحة
3. ✅ تم بناء ملفات JavaScript (`npm run build`)
4. ✅ المستخدم من نوع `admin`

---

*آخر تحديث: January 2026*

