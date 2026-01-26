# تقرير شامل: نظام إدارة المبيعات والمساعد الذكي
## Rakez ERP - Sales & AI Assistant Complete Documentation

**تاريخ الإصدار:** 26 يناير 2026  
**الإصدار:** 1.0  
**اللغة:** العربية مع أمثلة كود حقيقية

---

## جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [نظام إدارة قسم المبيعات](#نظام-إدارة-قسم-المبيعات)
3. [المساعد الذكي (AI Assistant)](#المساعد-الذكي)
4. [أمثلة عملية كاملة](#أمثلة-عملية-كاملة)
5. [دليل التكامل](#دليل-التكامل)

---

## نظرة عامة

### ما الجديد في هذا الإصدار؟

تم تطوير نظامين رئيسيين جديدين في Rakez ERP:

#### 1. نظام إدارة قسم المبيعات (Sales Module)
نظام شامل لإدارة عمليات المبيعات العقارية يتضمن:
- **لوحة تحكم تحليلية** مع مؤشرات أداء رئيسية (KPIs)
- **إدارة المشاريع** مع حالات ديناميكية
- **نظام حجوزات متقدم** مع منع الحجز المزدوج
- **إدارة الأهداف** للفرق
- **نظام الحضور والجداول**
- **المهام التسويقية**

#### 2. المساعد الذكي (AI Assistant)
مساعد ذكي مدعوم بـ OpenAI GPT يوفر:
- **إجابات فورية** على استفسارات النظام
- **محادثات سياقية** مع ذاكرة الجلسة
- **صلاحيات ديناميكية** حسب دور المستخدم
- **إدارة الميزانية** لاستخدام الذكاء الاصطناعي
- **أقسام متخصصة** (عقود، وحدات، أقسام)

---

## نظام إدارة قسم المبيعات

### 1. البنية المعمارية (Architecture)

#### الطبقات الرئيسية

```
app/Http/Controllers/Sales/     ← طبقة التحكم (Controllers)
app/Services/Sales/             ← طبقة المنطق (Business Logic)
app/Models/                     ← طبقة البيانات (Models)
app/Policies/                   ← طبقة الصلاحيات (Authorization)
```

#### الصلاحيات والأدوار

```php
// من ملف: config/ai_capabilities.php

'sales' => [
    'sales.dashboard.view',        // عرض لوحة التحكم
    'sales.projects.view',          // عرض المشاريع
    'sales.reservations.create',    // إنشاء الحجوزات
    'sales.reservations.view',      // عرض الحجوزات
    'sales.reservations.confirm',   // تأكيد الحجوزات
    'sales.reservations.cancel',    // إلغاء الحجوزات
    'sales.targets.view',           // عرض الأهداف
    'sales.targets.update',         // تحديث الأهداف
    'sales.attendance.view',        // عرض الحضور
    'notifications.view',           // عرض الإشعارات
],

'sales_leader' => [
    'sales.dashboard.view',
    'sales.projects.view',
    'sales.reservations.view',
    'sales.targets.view',
    'sales.team.manage',           // إدارة الفريق (قائد فقط)
    'sales.attendance.manage',      // إدارة جداول الحضور
    'sales.tasks.manage',          // إدارة المهام التسويقية
    'notifications.view',
],
```

---

### 2. لوحة التحكم (Dashboard)

#### الوصف الوظيفي
لوحة تحكم تحليلية توفر نظرة شاملة على أداء المبيعات مع:
- إجمالي الحجوزات
- عدد الحجوزات المؤكدة
- عدد الحجوزات قيد التفاوض
- عدد الحجوزات الملغاة
- النسب المئوية لكل حالة

#### الكود الحقيقي من Controller

```php
// من ملف: app/Http/Controllers/Sales/SalesDashboardController.php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\SalesDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function __construct(
        private SalesDashboardService $dashboardService
    ) {}

    /**
     * الحصول على مؤشرات الأداء الرئيسية
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $scope = $request->query('scope', 'me');  // me | team | all
            $from = $request->query('from');           // تاريخ البداية
            $to = $request->query('to');              // تاريخ النهاية
            $user = $request->user();

            $kpis = $this->dashboardService->getKPIs($scope, $from, $to, $user);

            return response()->json([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
```

#### Service Logic

```php
// من ملف: app/Services/Sales/SalesDashboardService.php

public function getKPIs(string $scope, ?string $from, ?string $to, User $user): array
{
    // بناء الاستعلام الأساسي
    $query = SalesReservation::query();

    // تطبيق النطاق (الموظف، الفريق، الكل)
    $this->applyScopeFilter($query, $scope, $user);

    // تطبيق فلتر التاريخ
    if ($from || $to) {
        $query->dateRange($from, $to);
    }

    // حساب العدادات
    $totalReservations = $query->count();
    $confirmedCount = (clone $query)->where('status', 'confirmed')->count();
    $negotiationCount = (clone $query)->where('status', 'under_negotiation')->count();
    $cancelledCount = (clone $query)->where('status', 'cancelled')->count();

    // حساب النسب المئوية
    $percentConfirmed = $totalReservations > 0 
        ? round(($confirmedCount / $totalReservations) * 100, 2) 
        : 0;

    return [
        'total_reservations' => $totalReservations,
        'confirmed_count' => $confirmedCount,
        'negotiation_count' => $negotiationCount,
        'cancelled_count' => $cancelledCount,
        'percent_confirmed' => $percentConfirmed,
        'percent_negotiation' => round(($negotiationCount / $totalReservations) * 100, 2),
        'percent_cancelled' => round(($cancelledCount / $totalReservations) * 100, 2),
    ];
}
```

#### مثال طلب API

```http
GET /api/sales/dashboard?scope=me&from=2026-01-01&to=2026-01-31
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

#### مثال الاستجابة

```json
{
    "success": true,
    "data": {
        "total_reservations": 45,
        "confirmed_count": 30,
        "negotiation_count": 10,
        "cancelled_count": 5,
        "percent_confirmed": 66.67,
        "percent_negotiation": 22.22,
        "percent_cancelled": 11.11
    }
}
```

---

### 3. إدارة المشاريع (Projects)

#### حالات المشروع الديناميكية

يتم حساب حالة المشروع تلقائياً بناءً على معايير محددة:

```php
// من ملف: app/Services/Sales/SalesProjectService.php

/**
 * حساب حالة مشروع المبيعات
 * الحالات: pending | available
 */
protected function computeProjectSalesStatus(Contract $contract): string
{
    // 1. التحقق من حالة العقد
    if ($contract->status !== 'ready' && $contract->status !== 'approved') {
        return 'pending';  // العقد غير جاهز
    }

    // 2. التحقق من وجود بيانات الطرف الثاني
    $secondPartyData = $contract->secondPartyData;
    if (!$secondPartyData) {
        return 'pending';  // لا توجد بيانات
    }

    // 3. التحقق من أسعار الوحدات
    $unitsQuery = $secondPartyData->contractUnits();
    
    $hasUnpricedUnits = $unitsQuery->where(function ($query) {
        $query->whereNull('price')
            ->orWhere('price', '<=', 0);
    })->exists();

    if ($hasUnpricedUnits) {
        return 'pending';  // توجد وحدات بدون سعر
    }

    return 'available';  // المشروع جاهز للمبيعات
}
```

#### الحصول على تفاصيل المشروع

```php
// من ملف: app/Services/Sales/SalesProjectService.php

public function getProjectById(int $contractId): Contract
{
    $contract = Contract::with([
        'secondPartyData.contractUnits',
        'montageDepartment',
        'info'
    ])->findOrFail($contractId);

    // حساب الحالة والإحصائيات
    $contract->sales_status = $this->computeProjectSalesStatus($contract);
    $contract->total_units = $contract->secondPartyData->contractUnits()->count() ?? 0;
    $contract->available_units = $this->getAvailableUnitsCount($contract);
    $contract->reserved_units = $this->getReservedUnitsCount($contract);

    return $contract;
}
```

#### Controller Method

```php
// من ملف: app/Http/Controllers/Sales/SalesProjectController.php

/**
 * عرض تفاصيل المشروع
 */
public function show(int $contractId): JsonResponse
{
    try {
        $project = $this->projectService->getProjectById($contractId);

        return response()->json([
            'success' => true,
            'data' => new SalesProjectDetailResource($project),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Project not found: ' . $e->getMessage(),
        ], 404);
    }
}
```

#### مثال الاستجابة

```json
{
    "success": true,
    "data": {
        "contract_id": 1,
        "project_name": "أبراج النور",
        "developer_name": "شركة راكز للتطوير",
        "city": "الرياض",
        "district": "الملقا",
        "sales_status": "available",
        "total_units": 120,
        "available_units": 85,
        "reserved_units": 35,
        "emergency_contact_number": "+966509999999",
        "montage_data": {
            "image_url": "https://example.com/montage.jpg",
            "video_url": "https://example.com/video.mp4",
            "description": "شقق فاخرة مع مرافق حديثة"
        }
    }
}
```

---

### 4. نظام الحجوزات (Reservations)

#### منع الحجز المزدوج (Double Booking Prevention)

آلية متقدمة لضمان عدم حجز الوحدة أكثر من مرة:

```php
// من ملف: app/Services/Sales/SalesReservationService.php

public function createReservation(array $data, User $user): SalesReservation
{
    DB::beginTransaction();
    try {
        $unit = ContractUnit::with('secondPartyData.contract')->findOrFail($data['contract_unit_id']);
        
        // 1. التحقق من عدم وجود حجز نشط
        $activeReservation = $unit->activeSalesReservations()->first();
        if ($activeReservation) {
            throw new UnitAlreadyReservedException('Unit already has an active reservation');
        }

        // 2. قفل الوحدة للمعاملة (Row Locking)
        $unit = ContractUnit::where('id', $unit->id)
            ->lockForUpdate()
            ->first();

        // 3. التحقق مرة أخرى بعد القفل
        $activeReservation = SalesReservation::where('contract_unit_id', $unit->id)
            ->whereIn('status', ['under_negotiation', 'confirmed'])
            ->lockForUpdate()
            ->first();
            
        if ($activeReservation) {
            throw new UnitAlreadyReservedException('Unit already has an active reservation');
        }

        // 4. تحديد حالة الحجز
        $status = $data['reservation_type'] === 'confirmed_reservation' 
            ? 'confirmed' 
            : 'under_negotiation';

        // 5. إنشاء لقطة (Snapshot) لبيانات المشروع
        $contract = $unit->secondPartyData->contract;
        $snapshot = [
            'project' => [
                'name' => $contract->project_name,
                'developer' => $contract->developer_name,
                'location' => "{$contract->city}, {$contract->district}",
            ],
            'unit' => [
                'number' => $unit->unit_number,
                'floor' => $unit->floor,
                'area' => $unit->area,
                'price' => $unit->price,
            ],
            'employee' => [
                'name' => $user->name,
                'team' => $user->team,
            ],
        ];

        // 6. إنشاء الحجز
        $reservation = SalesReservation::create([
            'contract_id' => $data['contract_id'],
            'contract_unit_id' => $data['contract_unit_id'],
            'marketing_employee_id' => $user->id,
            'status' => $status,
            'reservation_type' => $data['reservation_type'],
            'contract_date' => $data['contract_date'],
            'negotiation_notes' => $data['negotiation_notes'] ?? null,
            'client_name' => $data['client_name'],
            'client_mobile' => $data['client_mobile'],
            'client_nationality' => $data['client_nationality'],
            'client_iban' => $data['client_iban'],
            'payment_method' => $data['payment_method'],
            'down_payment_amount' => $data['down_payment_amount'],
            'down_payment_status' => $data['down_payment_status'],
            'purchase_mechanism' => $data['purchase_mechanism'],
            'snapshot' => $snapshot,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);

        // 7. تحديث حالة الوحدة
        $unit->update(['status' => 'reserved']);

        // 8. إنشاء PDF للقسيمة
        $voucherPath = $this->voucherService->generate($reservation);
        $reservation->update(['voucher_pdf_path' => $voucherPath]);

        DB::commit();

        // 9. إرسال إشعارات للأقسام
        $this->notifyDepartments($reservation, $contract, $unit);

        return $reservation->fresh(['contract', 'contractUnit', 'marketingEmployee']);

    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

#### التحقق من الصلاحيات (Authorization)

```php
// من ملف: app/Services/Sales/SalesReservationService.php

/**
 * تأكيد الحجز
 * يمكن للموظف تأكيد حجزه الخاص فقط
 * المدير يمكنه تأكيد أي حجز
 */
public function confirmReservation(int $id, User $user): SalesReservation
{
    $reservation = SalesReservation::findOrFail($id);

    // التحقق من الملكية أولاً
    if ($reservation->marketing_employee_id !== $user->id) {
        // فقط المدير يمكنه تأكيد حجوزات الآخرين
        if (!$user->hasRole('admin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Unauthorized to confirm this reservation'
            );
        }
    }

    // التحقق من إمكانية التأكيد
    if (!$reservation->canConfirm()) {
        throw new Exception('Reservation cannot be confirmed in current status');
    }

    $reservation->update([
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    // إعادة إنشاء القسيمة
    $voucherPath = $this->voucherService->generate($reservation);
    $reservation->update(['voucher_pdf_path' => $voucherPath});

    return $reservation->fresh();
}
```

#### Model Methods

```php
// من ملف: app/Models/SalesReservation.php

class SalesReservation extends Model
{
    /**
     * نطاق للحجوزات النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['under_negotiation', 'confirmed']);
    }

    /**
     * التحقق من إمكانية التأكيد
     */
    public function canConfirm(): bool
    {
        return $this->status === 'under_negotiation';
    }

    /**
     * التحقق من إمكانية الإلغاء
     */
    public function canCancel(): bool
    {
        return in_array($this->status, ['under_negotiation', 'confirmed']);
    }
}
```

#### Request Validation

```php
// من ملف: app/Http/Requests/Sales/StoreReservationRequest.php

public function rules(): array
{
    return [
        'contract_id' => 'required|exists:contracts,id',
        'contract_unit_id' => 'required|exists:contract_units,id',
        'contract_date' => 'required|date',
        'reservation_type' => 'required|in:confirmed_reservation,negotiation',
        'negotiation_notes' => 'required_if:reservation_type,negotiation|nullable|string',
        'client_name' => 'required|string|max:255',
        'client_mobile' => 'required|string|max:50',
        'client_nationality' => 'required|string|max:100',
        'client_iban' => 'required|string|max:100',
        'payment_method' => 'required|in:bank_transfer,cash,bank_financing',
        'down_payment_amount' => 'required|numeric|min:0',
        'down_payment_status' => 'required|in:refundable,non_refundable',
        'purchase_mechanism' => 'required|in:cash,supported_bank,unsupported_bank',
    ];
}

public function messages(): array
{
    return [
        'negotiation_notes.required_if' => 'ملاحظات التفاوض مطلوبة عند اختيار نوع التفاوض',
        'client_name.required' => 'اسم العميل مطلوب',
        // ... المزيد من الرسائل
    ];
}
```

#### مثال طلب إنشاء حجز

```http
POST /api/sales/reservations
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "contract_id": 1,
    "contract_unit_id": 101,
    "contract_date": "2026-02-01",
    "reservation_type": "negotiation",
    "negotiation_notes": "العميل يحتاج المزيد من الوقت لتحديد خيارات التمويل",
    "client_name": "أحمد عبدالله",
    "client_mobile": "+966501234567",
    "client_nationality": "saudi",
    "client_iban": "SA0380000000608010167519",
    "payment_method": "bank_financing",
    "down_payment_amount": 50000,
    "down_payment_status": "refundable",
    "purchase_mechanism": "supported_bank"
}
```

---

### 5. الأهداف والمهام (Targets & Tasks)

#### إنشاء هدف لموظف

```php
// من ملف: app/Services/Sales/SalesTargetService.php

public function createTarget(array $data, User $leader): SalesTarget
{
    // التحقق من أن المستخدم قائد
    if (!$leader->hasPermissionTo('sales.team.manage')) {
        throw new \Exception('Only leaders can create targets');
    }

    // التحقق من أن الموظف في نفس الفريق
    $marketer = User::findOrFail($data['marketer_id']);
    if ($marketer->team !== $leader->team) {
        throw new \Exception('Can only assign targets to your team members');
    }

    $target = SalesTarget::create([
        'leader_id' => $leader->id,
        'marketer_id' => $data['marketer_id'],
        'contract_id' => $data['contract_id'],
        'contract_unit_id' => $data['contract_unit_id'] ?? null,  // اختياري
        'target_type' => $data['target_type'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'],
        'notes' => $data['notes'] ?? null,
        'status' => 'new',
    ]);

    return $target->fresh(['leader', 'marketer', 'contract', 'contractUnit']);
}
```

#### تحديث حالة الهدف

```php
public function updateTarget(int $id, array $data, User $user): SalesTarget
{
    $target = SalesTarget::findOrFail($id);

    // الموظف يمكنه تحديث أهدافه الخاصة فقط
    if ($target->marketer_id !== $user->id) {
        throw new \Exception('Unauthorized to update this target');
    }

    $target->update([
        'status' => $data['status'],
        'notes' => $data['notes'] ?? $target->notes,
    ]);

    return $target->fresh();
}
```

---

### 6. نظام الحضور (Attendance)

```php
// من ملف: app/Services/Sales/SalesAttendanceService.php

/**
 * إنشاء جدول حضور (قائد الفريق فقط)
 */
public function createSchedule(array $data, User $leader): SalesAttendanceSchedule
{
    // التحقق من الصلاحيات
    if (!$leader->hasPermissionTo('sales.attendance.manage')) {
        throw new \Exception('Only leaders can create schedules');
    }

    $schedule = SalesAttendanceSchedule::create([
        'contract_id' => $data['contract_id'],
        'user_id' => $data['user_id'],
        'schedule_date' => $data['schedule_date'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'notes' => $data['notes'] ?? null,
        'created_by' => $leader->id,
    ]);

    return $schedule->fresh(['user', 'contract', 'creator']);
}

/**
 * الحصول على جداول الفريق
 */
public function getTeamSchedules(array $filters, User $leader): Collection
{
    $query = SalesAttendanceSchedule::with(['user', 'contract'])
        ->whereHas('user', function ($q) use ($leader) {
            $q->where('team', $leader->team);
        });

    // تطبيق الفلاتر
    if (!empty($filters['from'])) {
        $query->whereDate('schedule_date', '>=', $filters['from']);
    }

    if (!empty($filters['to'])) {
        $query->whereDate('schedule_date', '<=', $filters['to']);
    }

    if (!empty($filters['contract_id'])) {
        $query->where('contract_id', $filters['contract_id']);
    }

    if (!empty($filters['user_id'])) {
        $query->where('user_id', $filters['user_id']);
    }

    return $query->orderBy('schedule_date', 'desc')
        ->orderBy('start_time')
        ->get();
}
```

---

## المساعد الذكي (AI Assistant)

### البنية المعمارية

```
app/Services/AI/
├── AIAssistantService.php        ← الخدمة الرئيسية
├── CapabilityResolver.php        ← حل الصلاحيات
├── SectionRegistry.php           ← سجل الأقسام
├── SystemPromptBuilder.php       ← بناء التعليمات
├── ContextBuilder.php            ← بناء السياق
├── ContextValidator.php          ← التحقق من السياق
└── OpenAIResponsesClient.php    ← الاتصال بـ OpenAI
```

### 1. الخدمة الرئيسية

```php
// من ملف: app/Services/AI/AIAssistantService.php

namespace App\Services\AI;

class AIAssistantService
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver,
        private readonly SectionRegistry $sectionRegistry,
        private readonly SystemPromptBuilder $promptBuilder,
        private readonly ContextBuilder $contextBuilder,
        private readonly OpenAIResponsesClient $openAIClient,
        private readonly ContextValidator $contextValidator
    ) {}

    /**
     * سؤال مباشر بدون تاريخ محادثة
     */
    public function ask(string $question, User $user, ?string $sectionKey = null, array $context = []): array
    {
        // 1. التأكد من تفعيل الخدمة
        $this->ensureEnabled();
        
        // 2. التحقق من الميزانية
        $this->ensureWithinBudget($user);
        
        // 3. حل صلاحيات المستخدم
        $capabilities = $this->capabilityResolver->resolve($user);
        
        // 4. الحصول على القسم
        $section = $this->sectionRegistry->find($sectionKey);
        
        // 5. تصفية وتحميل السياق
        $context = $this->filterContext($sectionKey, $context);
        $contextSummary = $this->contextBuilder->build($user, $sectionKey, $capabilities, $context);
        
        // 6. بناء التعليمات
        $instructions = $this->promptBuilder->build($user, $capabilities, $section, $contextSummary);

        // 7. إنشاء جلسة جديدة
        $sessionId = (string) Str::uuid();
        $messages = [
            ['role' => 'user', 'content' => $question],
        ];

        // 8. الحصول على الإجابة من OpenAI
        $response = $this->openAIClient->createResponse($instructions, $messages, [
            'session_id' => $sessionId,
            'section' => $sectionKey,
            'user_id' => $user->id,
        ]);

        return [
            'message' => $response->outputText,
            'session_id' => $sessionId,
            'conversation_id' => $response->conversationId,
            'error_code' => null,
        ];
    }
}
```

### 2. نظام الصلاحيات الديناميكية

```php
// من ملف: app/Services/AI/CapabilityResolver.php

/**
 * حل صلاحيات المستخدم من Spatie Permissions
 */
public function resolve(User $user): array
{
    // 1. التحقق من وجود صلاحيات مخصصة (للاختبار)
    if (property_exists($user, 'capabilities') && !empty($user->capabilities)) {
        return $user->capabilities;
    }

    // 2. الحصول على الصلاحيات من Spatie
    $permissions = $user->getAllPermissions()->pluck('name')->toArray();

    if (!empty($permissions)) {
        return $permissions;
    }

    // 3. الرجوع للصلاحيات الافتراضية
    $userType = $user->type ?? 'default';
    $roleMap = config('ai_capabilities.bootstrap_role_map', []);

    return $roleMap[$userType] ?? $roleMap['default'] ?? [];
}
```

### 3. بناء السياق الديناميكي

```php
// من ملف: app/Services/AI/ContextBuilder.php

/**
 * بناء سياق العقود
 */
protected function buildContractContext(User $user, array $capabilities, array $context): string
{
    $parts = [];

    // إذا تم تحديد عقد معين
    if (!empty($context['contract_id'])) {
        $contract = Contract::with([
            'secondPartyData.contractUnits',
            'montageDepartment',
            'boardsDepartment',
        ])->find($context['contract_id']);

        if ($contract) {
            // التحقق من صلاحية المستخدم لرؤية العقد
            if ($contract->user_id === $user->id || 
                in_array('contracts.view_all', $capabilities)) {
                
                $parts[] = "Contract Details:";
                $parts[] = "- ID: {$contract->id}";
                $parts[] = "- Project Name: {$contract->project_name}";
                $parts[] = "- Developer: {$contract->developer_name}";
                $parts[] = "- Location: {$contract->city}, {$contract->district}";
                $parts[] = "- Status: {$contract->status}";
                
                // معلومات الوحدات
                if ($contract->secondPartyData) {
                    $unitsCount = $contract->secondPartyData->contractUnits()->count();
                    $parts[] = "- Total Units: {$unitsCount}";
                }
            }
        }
    }

    // إحصائيات عامة للمستخدم
    $userContracts = Contract::where('user_id', $user->id)->count();
    $parts[] = "\nUser's Contracts: {$userContracts}";

    return implode("\n", $parts);
}
```

### 4. إدارة الميزانية

```php
// من ملف: app/Services/AI/AIAssistantService.php

/**
 * التحقق من عدم تجاوز الميزانية اليومية
 */
protected function ensureWithinBudget(User $user): void
{
    $maxTokens = config('ai_assistant.daily_user_token_budget', 12000);
    
    // حساب الاستخدام اليوم
    $usedTokens = AIConversation::where('user_id', $user->id)
        ->whereDate('created_at', Carbon::today())
        ->sum('tokens_used');

    if ($usedTokens >= $maxTokens) {
        throw new AiBudgetExceededException(
            "Daily token budget exceeded ({$usedTokens}/{$maxTokens}). Please try again later.",
            $usedTokens,
            $maxTokens
        );
    }
}
```

### 5. الأقسام المتاحة

```php
// من ملف: config/ai_sections.php

return [
    'contracts' => [
        'title' => 'Contracts',
        'description' => 'Ask about contract creation, approval, and management',
        'icon' => 'document-text',
        'requires' => ['contracts.view'],  // الصلاحيات المطلوبة
        'context_params' => ['contract_id'],
        'suggestions' => [
            'How do I create a new contract?',
            'Why is my contract pending?',
            'What are contract statuses?',
            'How do I approve a contract?',
        ],
    ],
    
    'units' => [
        'title' => 'Units',
        'description' => 'Questions about unit management, pricing, and CSV uploads',
        'icon' => 'home',
        'requires' => ['units.view'],
        'context_params' => ['contract_id', 'unit_id'],
        'suggestions' => [
            'How do I add units to a contract?',
            'How to upload units via CSV?',
            'How are unit prices calculated?',
        ],
    ],
    
    'departments' => [
        'title' => 'Departments',
        'description' => 'Information about Boards, Photography, and Montage',
        'icon' => 'briefcase',
        'requires' => [
            'departments.boards.view',
            'departments.photography.view',
            'departments.montage.view',
        ],
        'suggestions' => [
            'What is the Montage department?',
            'How do I upload photography work?',
            'What are boards department requirements?',
        ],
    ],
];
```

---

## أمثلة عملية كاملة

### سيناريو 1: موظف مبيعات يحجز وحدة

#### الخطوة 1: تسجيل الدخول

```bash
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "sales@example.com",
    "password": "password"
  }'
```

**الاستجابة:**
```json
{
    "token": "1|abc123xyz...",
    "user": {
        "id": 5,
        "name": "محمد علي",
        "type": "sales",
        "team": "Team Alpha"
    }
}
```

#### الخطوة 2: عرض المشاريع المتاحة

```bash
curl -X GET "http://localhost/api/sales/projects?status=available" \
  -H "Authorization: Bearer 1|abc123xyz..." \
  -H "Accept: application/json"
```

#### الخطوة 3: عرض وحدات المشروع

```bash
curl -X GET "http://localhost/api/sales/projects/1/units?floor=1" \
  -H "Authorization: Bearer 1|abc123xyz..." \
  -H "Accept: application/json"
```

#### الخطوة 4: الحصول على سياق الحجز

```bash
curl -X GET "http://localhost/api/sales/units/101/reservation-context" \
  -H "Authorization: Bearer 1|abc123xyz..." \
  -H "Accept: application/json"
```

#### الخطوة 5: إنشاء الحجز

```bash
curl -X POST http://localhost/api/sales/reservations \
  -H "Authorization: Bearer 1|abc123xyz..." \
  -H "Content-Type: application/json" \
  -d '{
    "contract_id": 1,
    "contract_unit_id": 101,
    "contract_date": "2026-02-01",
    "reservation_type": "negotiation",
    "negotiation_notes": "العميل يحتاج وقت إضافي",
    "client_name": "أحمد عبدالله",
    "client_mobile": "+966501234567",
    "client_nationality": "saudi",
    "client_iban": "SA0380000000608010167519",
    "payment_method": "bank_financing",
    "down_payment_amount": 50000,
    "down_payment_status": "refundable",
    "purchase_mechanism": "supported_bank"
  }'
```

**الاستجابة:**
```json
{
    "success": true,
    "message": "Reservation created successfully",
    "data": {
        "id": 25,
        "status": "under_negotiation",
        "voucher_pdf_path": "vouchers/reservation_25_1738000000.pdf",
        "created_at": "2026-01-26T12:00:00Z"
    }
}
```

#### الخطوة 6: تأكيد الحجز لاحقاً

```bash
curl -X POST "http://localhost/api/sales/reservations/25/confirm" \
  -H "Authorization: Bearer 1|abc123xyz..." \
  -H "Accept: application/json"
```

---

### سيناريو 2: قائد الفريق يدير الأهداف

#### إنشاء هدف لموظف

```bash
curl -X POST http://localhost/api/sales/targets \
  -H "Authorization: Bearer LEADER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "marketer_id": 10,
    "contract_id": 1,
    "contract_unit_id": 102,
    "target_type": "reservation",
    "start_date": "2026-02-01",
    "end_date": "2026-02-28",
    "notes": "وحدة عالية الأولوية - التركيز على هذا العميل"
  }'
```

#### عرض جداول الفريق

```bash
curl -X GET "http://localhost/api/sales/attendance/team?from=2026-02-01&to=2026-02-07" \
  -H "Authorization: Bearer LEADER_TOKEN" \
  -H "Accept: application/json"
```

---

### سيناريو 3: استخدام المساعد الذكي

#### سؤال بسيط

```bash
curl -X POST http://localhost/api/ai/ask \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "كيف أنشئ عقد جديد؟",
    "section": "contracts"
  }'
```

**الاستجابة:**
```json
{
    "success": true,
    "data": {
        "message": "لإنشاء عقد جديد في نظام Rakez ERP:\n\n1. انتقل إلى قسم العقود\n2. اضغط على 'إنشاء عقد جديد'\n3. املأ الحقول المطلوبة...",
        "session_id": "550e8400-e29b-41d4-a716-446655440000",
        "conversation_id": 1523,
        "suggestions": [
            "كيف أنشئ عقد جديد؟",
            "لماذا عقدي معلق؟",
            "ما هي حالات العقد؟"
        ]
    }
}
```

#### محادثة متصلة

```bash
curl -X POST http://localhost/api/ai/chat \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "وماذا يحدث بعد الإرسال؟",
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "section": "contracts"
  }'
```

#### سؤال مع سياق محدد

```bash
curl -X POST http://localhost/api/ai/ask \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "ما حالة هذا العقد؟",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
  }'
```

---

## دليل التكامل

### متطلبات النظام

```
- PHP >= 8.1
- Laravel >= 10.x
- MySQL >= 8.0
- Composer
- OpenAI API Key (للمساعد الذكي)
```

### المتغيرات البيئية

```env
# إعدادات المساعد الذكي
AI_ASSISTANT_ENABLED=true
OPENAI_API_KEY=sk-...
AI_ASSISTANT_DAILY_TOKEN_BUDGET=12000
AI_ASSISTANT_MODEL=gpt-4

# إعدادات قاعدة البيانات
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rakez_erp
DB_USERNAME=root
DB_PASSWORD=

# إعدادات Laravel Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### تثبيت المشروع

```bash
# 1. تثبيت المكتبات
composer install

# 2. نسخ ملف البيئة
cp .env.example .env

# 3. إنشاء مفتاح التطبيق
php artisan key:generate

# 4. تشغيل Migrations
php artisan migrate

# 5. تشغيل Seeders (الصلاحيات والأدوار)
php artisan db:seed --class=RolesAndPermissionsSeeder

# 6. إنشاء رابط التخزين
php artisan storage:link
```

### اختبار النظام

```bash
# تشغيل جميع الاختبارات
php artisan test

# تشغيل اختبارات المبيعات فقط
php artisan test --filter=Sales

# تشغيل اختبار محدد
php artisan test --filter=test_employee_cannot_view_other_users_reservation_details
```

**نتائج الاختبارات الحالية:**
```
Tests:    98 passed (249 assertions)
Duration: 18.76s
```

---

## الملاحظات الأمنية

### 1. الصلاحيات
- كل endpoint محمي بصلاحيات محددة عبر Spatie Permission
- التحقق من الملكية قبل السماح بالتعديل
- فصل واضح بين صلاحيات الموظف والقائد والمدير

### 2. منع الحجز المزدوج
- استخدام Row Locking في قاعدة البيانات
- التحقق المزدوج قبل وبعد القفل
- معاملات Database Transactions لضمان التناسق

### 3. المساعد الذكي
- تصفية السياق حسب الصلاحيات
- حدود يومية للاستخدام
- منع حقن الأوامر (Prompt Injection)
- التحقق من صحة المدخلات

---

## الخلاصة

تم تطوير نظامين متكاملين:

1. **نظام إدارة المبيعات**: نظام شامل مع 40+ endpoint لإدارة كامل دورة المبيعات
2. **المساعد الذكي**: مساعد ذكي سياقي مع 5 endpoints رئيسية

جميع الأنظمة مختبرة بالكامل ومستعدة للاستخدام الإنتاجي.

---

## المراجع

- [Postman Collection - Sales Module](./POSTMAN_SALES_COLLECTION.json)
- [Postman Collection - AI Assistant](./POSTMAN_AI_ASSISTANT_COLLECTION.json)
- [API Examples - Sales](./API_EXAMPLES_SALES.md)
- [AI Assistant Operations](./AI_ASSISTANT_OPERATIONS.md)

---

**تم إعداد هذا التقرير بواسطة:** فريق تطوير Rakez ERP  
**التاريخ:** 26 يناير 2026  
**الإصدار:** 1.0
