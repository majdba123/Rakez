<?php

namespace Database\Seeders;

use App\Models\AssistantKnowledgeEntry;
use Illuminate\Database\Seeder;

class AssistantKnowledgeSalesSeeder extends Seeder
{
    /**
     * Seed sales module knowledge entries for the AI assistant.
     */
    public function run(): void
    {
        $entries = [
            // Project Status
            [
                'module' => 'sales',
                'page_key' => 'sales.projects.index',
                'title' => 'حالات المشروع في قسم المبيعات',
                'content_md' => $this->getProjectStatusContent(),
                'tags' => ['مشروع', 'حالة', 'متاح', 'معلق'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.projects.view'],
                'language' => 'ar',
                'priority' => 10,
            ],
            [
                'module' => 'sales',
                'page_key' => 'sales.projects.index',
                'title' => 'Project Status in Sales',
                'content_md' => $this->getProjectStatusContentEn(),
                'tags' => ['project', 'status', 'available', 'pending'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.projects.view'],
                'language' => 'en',
                'priority' => 10,
            ],

            // Reservation Form
            [
                'module' => 'sales',
                'page_key' => 'sales.reservations.create',
                'title' => 'كيفية إنشاء حجز وحدة',
                'content_md' => $this->getReservationFormContent(),
                'tags' => ['حجز', 'وحدة', 'نموذج', 'عميل'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.create'],
                'language' => 'ar',
                'priority' => 20,
            ],
            [
                'module' => 'sales',
                'page_key' => 'sales.reservations.create',
                'title' => 'How to Create a Unit Reservation',
                'content_md' => $this->getReservationFormContentEn(),
                'tags' => ['reservation', 'unit', 'form', 'client'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.create'],
                'language' => 'en',
                'priority' => 20,
            ],

            // Reservation Types
            [
                'module' => 'sales',
                'page_key' => 'sales.reservations.index',
                'title' => 'أنواع وحالات الحجز',
                'content_md' => $this->getReservationTypesContent(),
                'tags' => ['حجز', 'تفاوض', 'مؤكد', 'ملغي'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.view'],
                'language' => 'ar',
                'priority' => 25,
            ],
            [
                'module' => 'sales',
                'page_key' => 'sales.reservations.index',
                'title' => 'Reservation Types and Statuses',
                'content_md' => $this->getReservationTypesContentEn(),
                'tags' => ['reservation', 'negotiation', 'confirmed', 'cancelled'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.view'],
                'language' => 'en',
                'priority' => 25,
            ],

            // My Reservations
            [
                'module' => 'sales',
                'page_key' => 'sales.reservations.index',
                'title' => 'تبويبة حجوزاتي',
                'content_md' => $this->getMyReservationsContent(),
                'tags' => ['حجوزاتي', 'فلترة', 'عمليات'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.view'],
                'language' => 'ar',
                'priority' => 30,
            ],

            // Sales Dashboard
            [
                'module' => 'sales',
                'page_key' => 'sales.dashboard',
                'title' => 'داشبورد المبيعات',
                'content_md' => $this->getDashboardContent(),
                'tags' => ['داشبورد', 'مؤشرات', 'إحصائيات'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.dashboard.view'],
                'language' => 'ar',
                'priority' => 5,
            ],
            [
                'module' => 'sales',
                'page_key' => 'sales.dashboard',
                'title' => 'Sales Dashboard',
                'content_md' => $this->getDashboardContentEn(),
                'tags' => ['dashboard', 'KPIs', 'statistics'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.dashboard.view'],
                'language' => 'en',
                'priority' => 5,
            ],

            // My Goals
            [
                'module' => 'sales',
                'page_key' => 'sales.targets.index',
                'title' => 'تبويبة أهدافي',
                'content_md' => $this->getMyGoalsContent(),
                'tags' => ['أهداف', 'مهام', 'هدف'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.targets.view'],
                'language' => 'ar',
                'priority' => 40,
            ],

            // Team Management (Leader)
            [
                'module' => 'sales',
                'page_key' => 'sales.team.index',
                'title' => 'إدارة الفريق (قائد الفريق)',
                'content_md' => $this->getTeamManagementContent(),
                'tags' => ['فريق', 'قائد', 'إدارة', 'إسناد'],
                'roles' => ['sales_leader', 'admin'],
                'permissions' => ['sales.team.manage'],
                'language' => 'ar',
                'priority' => 50,
            ],

            // My Schedule
            [
                'module' => 'sales',
                'page_key' => 'sales.attendance.index',
                'title' => 'تبويبة دوامي',
                'content_md' => $this->getMyScheduleContent(),
                'tags' => ['دوام', 'جدول', 'مواعيد'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.attendance.view'],
                'language' => 'ar',
                'priority' => 60,
            ],

            // Team Schedule Management (Leader)
            [
                'module' => 'sales',
                'page_key' => 'sales.attendance.team',
                'title' => 'إدارة دوام الفريق (قائد الفريق)',
                'content_md' => $this->getTeamScheduleContent(),
                'tags' => ['دوام', 'فريق', 'جدولة', 'طوارئ'],
                'roles' => ['sales_leader', 'admin'],
                'permissions' => ['sales.attendance.manage'],
                'language' => 'ar',
                'priority' => 70,
            ],

            // Daily Tasks (Leader)
            [
                'module' => 'sales',
                'page_key' => 'sales.tasks.index',
                'title' => 'المهام اليومية (قائد الفريق)',
                'content_md' => $this->getDailyTasksContent(),
                'tags' => ['مهام', 'يومية', 'تسويق', 'تصميم'],
                'roles' => ['sales_leader', 'admin'],
                'permissions' => ['sales.tasks.manage'],
                'language' => 'ar',
                'priority' => 80,
            ],

            // Voucher PDF
            [
                'module' => 'sales',
                'page_key' => null,
                'title' => 'سند الحجز PDF',
                'content_md' => $this->getVoucherContent(),
                'tags' => ['سند', 'PDF', 'حجز', 'طباعة'],
                'roles' => ['sales', 'sales_leader', 'admin'],
                'permissions' => ['sales.reservations.view'],
                'language' => 'ar',
                'priority' => 35,
            ],
        ];

        foreach ($entries as $entry) {
            AssistantKnowledgeEntry::updateOrCreate(
                [
                    'module' => $entry['module'],
                    'page_key' => $entry['page_key'],
                    'language' => $entry['language'],
                    'title' => $entry['title'],
                ],
                array_merge($entry, ['is_active' => true])
            );
        }

        $this->command->info('✅ Sales knowledge entries seeded: ' . count($entries));
    }

    private function getProjectStatusContent(): string
    {
        return <<<'MD'
## حالات المشروع

### حالة "معلق" (Pending)
المشروع يظهر بحالة **معلق** في الحالات التالية:
- لم يتم اعتماد العقد بعد من إدارة المشاريع
- لم يتم إدخال أسعار الوحدات

### حالة "متاح" (Available)
المشروع يصبح **متاحاً** للحجز عندما:
- يكون العقد بحالة "جاهز" (Ready)
- جميع الوحدات لها أسعار محددة (أكبر من صفر)

### ملاحظة
لا يمكن حجز وحدات من مشروع في حالة "معلق". يجب انتظار إدارة المشاريع لإدخال الأسعار.
MD;
    }

    private function getProjectStatusContentEn(): string
    {
        return <<<'MD'
## Project Statuses

### "Pending" Status
A project shows as **Pending** when:
- The contract is not yet approved by Project Management
- Unit prices have not been entered

### "Available" Status
A project becomes **Available** for booking when:
- The contract status is "Ready"
- All units have prices set (greater than zero)

### Note
You cannot book units from a project in "Pending" status. Wait for Project Management to enter the prices.
MD;
    }

    private function getReservationFormContent(): string
    {
        return <<<'MD'
## نموذج حجز الوحدة

### البيانات التلقائية (للعرض فقط)
- اسم المشروع
- رقم الوحدة
- نوع الوحدة
- الحي / الموقع
- المساحة (م²)
- السعر الإجمالي
- اسم الموظف المسوّق
- فريق التسويق

### بيانات الحجز
- **تاريخ العقد**: تاريخ إبرام الحجز
- **نوع الحجز**:
  - حجز مؤكد (confirmed_reservation)
  - حجز بغرض التفاوض (negotiation)

### ملاحظات التفاوض
حقل نصي لكتابة تفاصيل الصفقة (إلزامي في حالة التفاوض)

### بيانات العميل
- اسم العميل (إلزامي)
- رقم جوال العميل (إلزامي)
- جنسية العميل (قائمة منسدلة)
- رقم IBAN

### بيانات الدفع
- **طريقة الدفع**: تحويل بنكي / كاش / تمويل بنكي
- **قيمة العربون**: بالريال السعودي
- **حالة العربون**: مسترد / غير مسترد
- **آلية الشراء**: كاش / بنك مدعوم / بنك غير مدعوم
MD;
    }

    private function getReservationFormContentEn(): string
    {
        return <<<'MD'
## Unit Reservation Form

### Auto-filled Data (Display Only)
- Project name
- Unit number
- Unit type
- District / Location
- Area (m²)
- Total price
- Marketing employee name
- Marketing team

### Reservation Data
- **Contract Date**: Date of the reservation
- **Reservation Type**:
  - Confirmed Reservation
  - Negotiation

### Negotiation Notes
Text field for deal details (required for negotiation type)

### Client Data
- Client name (required)
- Client mobile (required)
- Client nationality (dropdown)
- IBAN number

### Payment Data
- **Payment Method**: Bank Transfer / Cash / Bank Financing
- **Down Payment Amount**: In SAR
- **Down Payment Status**: Refundable / Non-refundable
- **Purchase Mechanism**: Cash / Supported Bank / Unsupported Bank
MD;
    }

    private function getReservationTypesContent(): string
    {
        return <<<'MD'
## أنواع الحجز

### حجز مؤكد (Confirmed Reservation)
- يتم تأكيد الحجز مباشرة
- يتم إصدار سند الحجز PDF
- يتم إشعار الأقسام المعنية

### حجز بغرض التفاوض (Negotiation)
- الحجز في مرحلة التفاوض
- يجب إدخال ملاحظات التفاوض
- يمكن تأكيده لاحقاً أو إلغاؤه

## حالات الحجز

| الحالة | الوصف |
|--------|-------|
| **قيد التفاوض** | الحجز في مرحلة التفاوض مع العميل |
| **مؤكد** | تم تأكيد الحجز بنجاح |
| **ملغي** | تم إلغاء الحجز |

### تأكيد الحجز
- يمكن تأكيد الحجوزات في حالة "قيد التفاوض" فقط
- عند التأكيد يتم إصدار سند حجز جديد

### إلغاء الحجز
- يمكن إلغاء الحجوزات في حالة "قيد التفاوض" أو "مؤكد"
- عند الإلغاء تعود الوحدة لحالة "متاحة"
MD;
    }

    private function getReservationTypesContentEn(): string
    {
        return <<<'MD'
## Reservation Types

### Confirmed Reservation
- Reservation is confirmed immediately
- PDF voucher is generated
- Relevant departments are notified

### Negotiation
- Reservation is in negotiation phase
- Negotiation notes are required
- Can be confirmed or cancelled later

## Reservation Statuses

| Status | Description |
|--------|-------------|
| **Under Negotiation** | Reservation is being negotiated with client |
| **Confirmed** | Reservation successfully confirmed |
| **Cancelled** | Reservation has been cancelled |

### Confirming a Reservation
- Only "Under Negotiation" reservations can be confirmed
- A new voucher PDF is generated upon confirmation

### Cancelling a Reservation
- Both "Under Negotiation" and "Confirmed" reservations can be cancelled
- The unit returns to "Available" status upon cancellation
MD;
    }

    private function getMyReservationsContent(): string
    {
        return <<<'MD'
## تبويبة حجوزاتي

### عرض الحجوزات
يمكنك عرض جميع الحجوزات التي قمت بها بما في ذلك:
- الحجوزات النشطة
- الحجوزات الملغاة (اختياري)

### تصفية الحجوزات
يمكنك تصفية الحجوزات حسب:
- **المشروع**: اختر مشروع محدد
- **الحالة**: قيد التفاوض / مؤكد / ملغي
- **التاريخ**: من تاريخ إلى تاريخ

### تسجيل العمليات
لكل حجز يمكنك تسجيل نوع العملية:
- **جلب**: جلب العميل للمشروع
- **إقناع**: إقناع العميل بالوحدة
- **إقفال**: إتمام الصفقة

**ملاحظة**: هذه العمليات تُحتسب تلقائياً في نظام العمولات.

### تحميل سند الحجز
يمكنك تحميل سند الحجز PDF لأي حجز من حجوزاتك.
MD;
    }

    private function getDashboardContent(): string
    {
        return <<<'MD'
## داشبورد المبيعات

### المؤشرات الرئيسية (KPIs)

| المؤشر | الوصف |
|--------|-------|
| **الوحدات المحجوزة** | عدد الوحدات التي عليها حجوزات نشطة |
| **الوحدات المتاحة** | عدد الوحدات المتاحة للحجز |
| **المشاريع قيد التسويق** | عدد المشاريع بحالة "جاهز" |
| **نسبة الحجوزات المؤكدة** | نسبة الحجوزات المؤكدة من إجمالي الحجوزات |

### نطاق العرض
يمكنك تحديد نطاق البيانات:
- **أنا**: حجوزاتي فقط
- **الفريق**: حجوزات فريقي
- **الكل**: جميع الحجوزات (للمدير فقط)

### فلترة بالتاريخ
يمكنك تحديد فترة زمنية لعرض الإحصائيات.
MD;
    }

    private function getDashboardContentEn(): string
    {
        return <<<'MD'
## Sales Dashboard

### Key Performance Indicators (KPIs)

| Indicator | Description |
|-----------|-------------|
| **Reserved Units** | Units with active reservations |
| **Available Units** | Units available for booking |
| **Projects Under Marketing** | Projects with "Ready" status |
| **Confirmation Rate** | Percentage of confirmed reservations |

### Display Scope
You can select the data scope:
- **Me**: My reservations only
- **Team**: My team's reservations
- **All**: All reservations (admin only)

### Date Filter
You can specify a date range for statistics.
MD;
    }

    private function getMyGoalsContent(): string
    {
        return <<<'MD'
## تبويبة أهدافي

### عرض الأهداف
تظهر قائمة بالأهداف المضافة من قائد الفريق.

### تفاصيل الهدف
كل هدف يحتوي على:
- **المشروع**: المشروع المرتبط بالهدف
- **الوحدة**: الوحدة المحددة (اختياري)
- **نوع الهدف**: حجز / تفاوض / إقفال
- **تاريخ البداية**: بداية فترة الهدف
- **تاريخ النهاية**: نهاية فترة الهدف
- **ملاحظات القائد**: توجيهات من القائد

### حالات الهدف
| الحالة | الوصف |
|--------|-------|
| **جديد** | هدف جديد لم يبدأ العمل عليه |
| **قيد التنفيذ** | جاري العمل على الهدف |
| **منجز** | تم إنجاز الهدف |

### تحديث الحالة
يمكنك تحديث حالة أهدافك فقط (لا يمكن تعديل أهداف الآخرين).
MD;
    }

    private function getTeamManagementContent(): string
    {
        return <<<'MD'
## إدارة الفريق (لقائد الفريق)

**ملاحظة**: هذه التبويبة تظهر فقط لقائد فريق المبيعات.

### عرض المشاريع
يمكنك عرض المشاريع المسندة إليك كقائد.

### عرض الوحدات
عند الضغط على مشروع تظهر الوحدات التابعة له.

### إسناد هدف لموظف
1. اضغط على الثلاث نقاط بجانب الوحدة
2. اختر الموظف من قائمة الفريق
3. حدد نوع الهدف
4. حدد تاريخ الاستحقاق
5. أضف ملاحظاتك

### بيانات الهدف
- **المشروع**: يتم تحديده تلقائياً
- **الوحدة**: اختياري
- **نوع الهدف**: حجز / تفاوض / إقفال
- **تاريخ الاستحقاق**: تاريخ البداية والنهاية
- **ملاحظات**: توجيهات للموظف
MD;
    }

    private function getMyScheduleContent(): string
    {
        return <<<'MD'
## تبويبة دوامي

### عرض الجدول
يمكنك عرض جدول دوامك حسب:
- **اليوم**: تاريخ الدوام
- **الوقت**: من الساعة إلى الساعة
- **المشروع**: المشروع المكلف به

### تصفية الجدول
يمكنك تصفية الجدول بالتاريخ (من / إلى).

### ملاحظة
الجدول يُحدد من قبل قائد الفريق. لا يمكنك تعديله.
MD;
    }

    private function getTeamScheduleContent(): string
    {
        return <<<'MD'
## إدارة دوام الفريق (لقائد الفريق)

**ملاحظة**: هذه التبويبة تظهر فقط لقائد فريق المبيعات.

### عرض المشاريع
تظهر المشاريع التي تم استكمالها (بحالة جاهز).

### جدولة الدوام
1. اضغط على المشروع
2. اختر اليوم
3. اضغط على زر الفلترة
4. اختر الموظف
5. حدد وقت الدوام (من / إلى)

### بيانات الطوارئ
يمكنك إضافة:
- **رقم الطوارئ**: رقم للتواصل في الحالات الطارئة
- **رقم حارس المشروع**: رقم حارس الأمن

### تصفية الجدول
يمكنك تصفية جدول الفريق حسب:
- الموظف
- المشروع
- التاريخ
MD;
    }

    private function getDailyTasksContent(): string
    {
        return <<<'MD'
## المهام اليومية (لقائد الفريق)

**ملاحظة**: هذه التبويبة تظهر فقط لقائد فريق المبيعات.

### عرض المشاريع
تظهر جميع المشاريع الخاصة بفريقك.

### تفاصيل المشروع
عند الضغط على مشروع يظهر:
- التصميمات من قسم المونتاج
- رابط الصور
- رابط الفيديوهات
- وصف المشروع

### إضافة مهمة لقسم التسويق
يمكنك إضافة مهمة جديدة بالبيانات التالية:
- **اسم المهمة**: مثل "حملة تواصل مباشر"
- **اسم المسوّق**: الموظف المسؤول
- **عدد المسوقين**: افتراضي 4
- **رابط التصميم**: رابط التصميم من المونتاج
- **رقم التصميم**: رقم مرجعي
- **وصف التصميم**: تفاصيل المطلوب

### حالات المهمة
| الحالة | الوصف |
|--------|-------|
| **جديد** | مهمة جديدة |
| **قيد التنفيذ** | جاري العمل عليها |
| **مكتمل** | تم إنجازها |
MD;
    }

    private function getVoucherContent(): string
    {
        return <<<'MD'
## سند الحجز (PDF)

### محتويات السند
يحتوي سند الحجز على:

#### بيانات المشروع
- اسم المشروع
- المدينة والحي
- اسم المطور
- رقم المطور

#### بيانات الوحدة
- رقم الوحدة
- نوع الوحدة
- المساحة
- الطابق
- السعر

#### بيانات العميل
- اسم العميل
- رقم الجوال
- الجنسية
- رقم IBAN

#### بيانات الحجز
- نوع الحجز
- تاريخ الحجز
- قيمة العربون
- طريقة الدفع
- آلية الشراء

#### بيانات الموظف
- اسم الموظف
- الفريق

### تحميل السند
يمكن تحميل السند من صفحة "حجوزاتي" بالضغط على زر التحميل.
MD;
    }
}

